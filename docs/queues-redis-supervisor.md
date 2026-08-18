# Laravel queues, Redis, Supervisor, and reliable HR notification email

The current BluePrint HR port already has Laravel’s Redis queue connection defined in `config/queue.php`, but its development `.env.example` and supplied Supervisor template use the database queue. That is a reasonable local default. For a production Ubuntu Server, Redis plus Supervisor is the preferred configuration for responsive background processing and separated email workloads.

## 1. Choose the queue backend

| Approach | Tradeoffs | Cost | Setup complexity |
|---|---|---:|---:|
| Laravel database queue | No additional service; simple to inspect in MySQL; more database contention and weaker throughput for bursts | Lowest | Low |
| Redis queue with Supervisor | Fast, supports queue priorities and separate workers; requires Redis operations and monitoring | Low when Redis is on the same server; higher when managed | Medium |
| Redis queue with Horizon | Adds a useful Redis queue dashboard and balancing controls; still needs a process supervisor and Redis | Medium | Medium-high |

For the HR application, use **Redis plus Supervisor** for email notifications, report generation, imports, and other asynchronous work. Keep the authoritative employee, payroll, leave, audit, and notification-delivery records in MySQL. Redis is the transport, not the financial system of record.

## 2. Install and secure Redis on Ubuntu

Install the Redis server, the PHP Redis extension, and Supervisor:

```bash
sudo apt update
sudo apt install -y redis-server php8.3-redis supervisor
sudo systemctl enable --now redis-server supervisor
redis-cli ping
# Expected: PONG
```

For a Redis instance on the same server, keep it private and local. In `/etc/redis/redis.conf`, verify that Redis is bound to localhost and protected mode remains enabled:

```conf
bind 127.0.0.1 ::1
protected-mode yes
port 6379
```

Restart Redis after configuration changes:

```bash
sudo systemctl restart redis-server
sudo systemctl status redis-server --no-pager
```

Do not expose port `6379` to the public Internet. If Redis is remote, use a private network, authentication or ACLs, TLS where supported, and a firewall rule limited to the application server. For a single-server deployment, the local-only configuration is simpler and safer.

Redis persistence is useful for reducing loss during a restart, but it does not turn a queue into a financial ledger. Configure AOF only after reviewing the server’s disk and backup policy. Payroll runs, payslip records, approval decisions, and audit events must remain persisted in MySQL.

## 3. Configure Laravel for Redis queues

The delivered application’s `config/database.php` already supports `phpredis`, and `config/queue.php` already defines a Redis connection. Set the production environment values in `.env`:

```dotenv
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=180
REDIS_QUEUE_BLOCK_FOR=5
CACHE_STORE=redis
```

The current port hard-codes the Redis queue `block_for` value as `null`. For a production worker that waits efficiently without permanently blocking shutdown signals, update the Redis connection in `config/queue.php` to:

```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180),
    'block_for' => (int) env('REDIS_QUEUE_BLOCK_FOR', 5),
    'after_commit' => true,
],
```

`after_commit=true` is important for HR workflows. If a leave approval, payroll run, or employee update creates a notification inside a database transaction, Laravel waits until the transaction commits before placing the queued job on Redis. This avoids a worker reading records that later roll back or do not yet exist.

The `retry_after` value must be longer than the worker’s `--timeout` value. A practical starting point is `--timeout=120` and `REDIS_QUEUE_RETRY_AFTER=180`. If `retry_after` is shorter than the worker timeout, a long-running job may be made visible again while the first worker is still processing it, causing duplicate work.

After editing `.env` or `config/queue.php`, rebuild the cached configuration:

```bash
cd /var/www/blueprint-hr
php artisan optimize:clear
php artisan config:cache
```

## 4. Organize queues by business impact

Do not put every job on one queue. Use named queues so a burst of employee emails cannot prevent a critical application job from running. A useful starting convention is:

| Queue | Typical jobs | Worker policy |
|---|---|---|
| `critical` | Security alerts, approval events, payroll completion events | Highest priority; one or more dedicated workers |
| `emails` | Leave decisions, onboarding messages, payslip delivery, reminders | Separate workers; retry transient mail failures |
| `reports` | Excel exports, PDF batches, imports | Isolated workers with higher timeout and memory |
| `default` | Ordinary asynchronous application work | Normal priority |

Dispatch work explicitly:

```php
ProcessEmployeeImport::dispatch($importId)
    ->onConnection('redis')
    ->onQueue('reports');

SendLeaveDecisionEmail::dispatch($leaveRequestId)
    ->onConnection('redis')
    ->onQueue('emails');
```

Pass model IDs or small immutable identifiers into jobs rather than serializing complete HR records, payroll arrays, or PDF contents. The job should re-load the current authorized records inside `handle()` and should verify tenant ownership before acting.

## 5. Queue HR notifications safely

Laravel notifications can implement `ShouldQueue`; Laravel then creates background delivery jobs for the configured notification channels. For HR email, use the database channel as an in-application record and the mail channel for delivery:

```php
<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(
        public int $leaveRequestId,
        public string $decision,
    ) {
        $this->onConnection('redis')->onQueue('emails');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = LeaveRequest::with(['employee', 'leaveType'])->findOrFail($this->leaveRequestId);

        return (new MailMessage)
            ->subject("Leave request {$this->decision}")
            ->greeting("Hello {$notifiable->name}")
            ->line("Your {$request->leaveType->name} request has been {$this->decision}.")
            ->line("Dates: {$request->start_date->toDateString()} to {$request->end_date->toDateString()}.");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequestId,
            'decision' => $this->decision,
        ];
    }
}
```

The example is illustrative; adapt the model relationships and date casts to the port’s final notification implementation. For payslips, pass the `PayrollTransaction` ID and generate or retrieve the PDF inside the worker rather than storing a sensitive PDF in the Redis payload.

For higher-risk workflows, add a `notification_deliveries` table with a unique key such as `tenant_id + event_type + event_id + recipient_id + channel`. Store `pending`, `sent`, and `failed` states, attempt counts, provider message IDs, timestamps, and the last error. This gives HR administrators an auditable delivery history and lets a repair command retry only the failed delivery. Email systems cannot guarantee exactly-once delivery when a provider accepts a message and the network times out, so application-level idempotency and delivery records are preferable to relying on retries alone.

For duplicate-prone reminders or recurring jobs, use a unique job key. For example, a monthly payroll reminder can use a unique identifier such as `payroll-reminder:{tenant}:{year-month}:{recipient}` and a bounded `uniqueFor` period. Never use a broad global lock that could prevent one tenant’s notification from running because another tenant has the same event type.

## 6. Configure transactional email

Laravel’s current documentation recommends API-based mail transports such as Postmark, Mailgun, Resend, or Amazon SES when possible. SMTP is also supported and is suitable when the organization already has a managed SMTP provider.

A TLS SMTP configuration looks like this:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=blueprint-hr@example.com
MAIL_PASSWORD=USE_A_SECRET_FROM_SECRET_MANAGEMENT
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@hr.example.com
MAIL_FROM_NAME="BluePrint HR"
```

The delivered `config/mail.php` currently has a `failover` mailer that falls back from SMTP to the `log` mailer. Do not use `log` as a production fallback because it records the message locally without delivering it. Configure two real delivery transports instead. For example:

```php
'mailers' => [
    'smtp_primary' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST'),
        'port' => env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'scheme' => env('MAIL_SCHEME', 'tls'),
        'timeout' => 20,
    ],

    'smtp_secondary' => [
        'transport' => 'smtp',
        'host' => env('MAIL_SECONDARY_HOST'),
        'port' => env('MAIL_SECONDARY_PORT', 587),
        'username' => env('MAIL_SECONDARY_USERNAME'),
        'password' => env('MAIL_SECONDARY_PASSWORD'),
        'scheme' => env('MAIL_SECONDARY_SCHEME', 'tls'),
        'timeout' => 20,
    ],

    'failover' => [
        'transport' => 'failover',
        'mailers' => ['smtp_primary', 'smtp_secondary'],
        'retry_after' => 60,
    ],
],
```

Then set:

```dotenv
MAIL_MAILER=failover
MAIL_SECONDARY_HOST=backup-smtp.example.com
MAIL_SECONDARY_PORT=587
MAIL_SECONDARY_USERNAME=backup-user
MAIL_SECONDARY_PASSWORD=USE_ANOTHER_SECRET
MAIL_SECONDARY_SCHEME=tls
```

A failover transport is not a substitute for queued retries, provider bounce monitoring, SPF/DKIM/DMARC, and a delivery audit record. Configure the primary and secondary provider domains correctly, verify the sender domain, and test both provider failure and normal delivery in staging.

## 7. Supervisor worker configuration

Supervisor keeps Laravel workers running, restarts them after crashes, and allows separate pools for different queue classes. Create `/etc/supervisor/conf.d/blueprint-hr-workers.conf`:

```ini
[program:blueprint-hr-critical]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/blueprint-hr
command=/usr/bin/php artisan queue:work redis --queue=critical,default --sleep=3 --tries=5 --timeout=120 --backoff=60 --max-time=3600 --memory=512
autostart=true
autorestart=true
startsecs=5
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/blueprint-hr-critical.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=10

[program:blueprint-hr-emails]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/blueprint-hr
command=/usr/bin/php artisan queue:work redis --queue=emails --sleep=3 --tries=5 --timeout=120 --backoff=60 --max-time=3600 --memory=512
autostart=true
autorestart=true
startsecs=5
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/blueprint-hr-emails.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=10

[program:blueprint-hr-reports]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/blueprint-hr
command=/usr/bin/php artisan queue:work redis --queue=reports --sleep=3 --tries=3 --timeout=300 --backoff=120 --max-time=3600 --memory=768
autostart=true
autorestart=true
startsecs=5
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/blueprint-hr-reports.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=10
```

The `emails` pool has two workers as a starting point because email delivery is I/O-bound. Increase or decrease it after measuring provider rate limits and queue latency. The `reports` pool has a longer timeout because Excel and PDF work can be heavier. Keep `retry_after` in `config/queue.php` longer than the longest worker timeout; if report workers use `--timeout=300`, use a Redis queue `retry_after` greater than 300, such as 360.

Install and activate the configuration:

```bash
sudo cp /var/www/blueprint-hr/deploy/supervisor/blueprint-hr-workers.conf \
    /etc/supervisor/conf.d/blueprint-hr-workers.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

If you keep the project’s existing single worker template instead, change its command from `queue:work database` to `queue:work redis` and add the appropriate `--queue`, `--timeout`, `--tries`, and `--memory` flags. Separate pools are preferable for HR workloads because email spikes should not delay payroll or approval jobs.

Do not expose Supervisor’s HTTP control interface publicly. Use the local Unix socket and `supervisorctl` over SSH. If an HTTP interface is deliberately enabled for an internal network, bind it to localhost or a protected private address and require authentication.

## 8. Start, restart, and deploy workers

After changing `.env`, queue configuration, mail configuration, or application code, clear and rebuild Laravel’s caches, then restart workers. Queue workers are long-lived processes and do not automatically load new code.

```bash
cd /var/www/blueprint-hr
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart blueprint-hr-critical:*
sudo supervisorctl restart blueprint-hr-emails:*
sudo supervisorctl restart blueprint-hr-reports:*
```

During a normal release, the safer Laravel-native restart signal is:

```bash
php artisan queue:restart
```

Supervisor will start the workers again after they exit. Use `supervisorctl restart` when configuration, process counts, or the Supervisor command itself changes.

## 9. Failed jobs and operational monitoring

The port includes Laravel’s failed-jobs storage migration. Confirm that `failed_jobs` exists in production, then use the following commands:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

Do not retry all failures blindly when payslips or statutory notifications are involved. Inspect the exception, verify whether the provider accepted the message, and use the application delivery record to avoid duplicate sends. Prefer a targeted retry by failed-job ID:

```bash
php artisan queue:retry FAILED_JOB_ID
```

Monitor both queue depth and age. For Redis, basic checks include:

```bash
redis-cli LLEN queues:emails
redis-cli LLEN queues:critical
redis-cli LLEN queues:reports
sudo supervisorctl status
sudo tail -f /var/log/blueprint-hr-emails.log
sudo journalctl -u redis-server -f
```

For a more complete Redis queue dashboard, Laravel Horizon is an optional addition. It is not required when Supervisor is used, but it can provide queue throughput, runtime, failure, and wait-time visibility. Horizon still needs Redis and a process supervisor.

## 10. Test notifications without sending real email

In automated tests, use `Notification::fake()`, `Mail::fake()`, and `Queue::fake()` to verify dispatch behavior without contacting an SMTP or API provider:

```php
Notification::fake();
Queue::fake();

$user->notify(new LeaveDecisionNotification($leaveRequest->id, 'Approved'));

Notification::assertSentTo($user, LeaveDecisionNotification::class);
```

For a staging smoke test, configure a real test mailbox or a provider sandbox, dispatch one leave decision and one payslip notification, inspect the `emails` queue, and verify the job leaves Redis and creates the expected database notification and delivery record. Test a temporary SMTP outage by using an invalid staging host and confirm that the job retries, records a failure, and eventually appears in `failed_jobs` after the configured attempts.

## 11. Recommended reliability rules for HR workflows

Use `after_commit` for jobs dispatched from employee, leave, and payroll transactions. Keep MySQL as the authoritative source for payroll, approvals, employee data, audit events, and notification-delivery state. Use Redis for fast transport and queue coordination. Set explicit per-job `tries`, `backoff`, `timeout`, and queue names. Ensure `retry_after` is longer than the corresponding worker timeout. Use a unique delivery key for important messages, and do not include raw payroll data or sensitive documents in Redis payloads. Generate payslip PDFs inside the authorized worker or store them in protected object storage with an access-controlled reference. Monitor failed jobs and queue latency, and restart workers after every code deployment.

## References

[1]: https://laravel.com/docs/12.x/queues Laravel 12 Queues documentation
[2]: https://laravel.com/docs/12.x/notifications Laravel 12 Notifications documentation
[3]: https://laravel.com/docs/12.x/mail Laravel 12 Mail documentation
[4]: https://laravel.com/docs/12.x/redis Laravel 12 Redis documentation
[5]: https://supervisord.org/configuration.html Supervisor configuration documentation
