<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const API_URL = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '');
const api = axios.create({ baseURL: API_URL });
const savedToken = localStorage.getItem('bp_token');
if (savedToken) api.defaults.headers.common.Authorization = `Bearer ${savedToken}`;

const user = ref(null);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const active = ref('dashboard');
const sidebarOpen = ref(false);
const darkMode = ref(localStorage.getItem('bp_dark') === '1');
const showEmployeeModal = ref(false);
const showLeaveModal = ref(false);
const showNotifications = ref(false);
const globalSearch = ref('');
const employeeSearch = ref('');
const employeeStatus = ref('All');
const leaveStatus = ref('All');
const dashboard = ref({ metrics: {}, employees: [], periods: [], recent_audit: [], tenant: null });
const employees = ref({ data: [] });
const organization = ref({ branches: [], departments: [], designations: [], grades: [], employment_types: [] });
const leave = ref({ types: [], balances: [], requests: [] });
const payroll = ref({ periods: [], transactions: [] });
const login = reactive({ email: '', password: '', remember: false });
const employeeForm = reactive({ employee_no:'', first_name:'', middle_name:'', last_name:'', email:'', phone:'', employment_status:'Active', basic_salary:0, branch_id:null, department_id:null });
const leaveForm = reactive({ employee_id:null, leave_type_id:null, start_date:'', end_date:'', days_requested:1, reason:'' });

const role = computed(() => user.value?.role?.value || user.value?.role || 'Employee');
const isEmployee = computed(() => role.value === 'Employee');
const canManagePeople = computed(() => ['Super Admin','Company Admin','HR Manager'].includes(role.value));
const canProcessPayroll = computed(() => ['Super Admin','Company Admin','Payroll Manager'].includes(role.value));
const money = value => new Intl.NumberFormat('en-KE', { style:'currency', currency:'KES', maximumFractionDigits:0 }).format(Number(value || 0));
const fullName = row => row?.full_name || [row?.first_name,row?.middle_name,row?.last_name].filter(Boolean).join(' ');
const initials = row => fullName(row).split(/\s+/).filter(Boolean).slice(0,2).map(x => x[0]).join('').toUpperCase() || 'BP';

const navItems = computed(() => [
  { key:'dashboard', label:'Overview', icon:'bi-grid-1x2-fill' },
  { key:'employees', label:'People', icon:'bi-people-fill' },
  { key:'leave', label:'Leave', icon:'bi-calendar2-week' },
  { key:'payroll', label:'Payroll', icon:'bi-wallet2' },
  { key:'organization', label:'Organization', icon:'bi-diagram-3-fill' },
  { key:'ess', label:'My workspace', icon:'bi-person-badge-fill' },
  { key:'reports', label:'Reports', icon:'bi-bar-chart-fill' },
  { key:'audit', label:'Audit trail', icon:'bi-shield-check' }
]);
const filteredEmployees = computed(() => {
  const q = employeeSearch.value.toLowerCase().trim();
  return (employees.value.data || []).filter(e => {
    const text = [fullName(e), e.employee_no, e.email, e.phone, e.department?.name].join(' ').toLowerCase();
    return (!q || text.includes(q)) && (employeeStatus.value === 'All' || e.employment_status === employeeStatus.value);
  });
});
const filteredLeave = computed(() => {
  const q = globalSearch.value.toLowerCase().trim();
  return (leave.value.requests || []).filter(r => {
    const text = [fullName(r.employee), r.leave_type?.name, r.status].join(' ').toLowerCase();
    return (!q || text.includes(q)) && (leaveStatus.value === 'All' || r.status === leaveStatus.value);
  });
});
const pendingLeave = computed(() => (leave.value.requests || []).filter(r => r.status === 'Pending'));
const payrollTotal = computed(() => (payroll.value.transactions || []).reduce((s,r) => s + Number(r.gross_pay || 0), 0));
const netPayroll = computed(() => (payroll.value.transactions || []).reduce((s,r) => s + Number(r.net_pay || 0), 0));
const departmentsCount = computed(() => organization.value.departments?.length || 0);
const workforceMix = computed(() => {
  const rows = employees.value.data || [];
  const activeCount = rows.filter(e => e.employment_status === 'Active').length;
  const inactive = rows.length - activeCount;
  return { active: activeCount, inactive, total: rows.length, activePct: rows.length ? Math.round(activeCount / rows.length * 100) : 0 };
});
const notifications = computed(() => [
  ...pendingLeave.value.slice(0,3).map(r => ({ icon:'bi-calendar-event', title:'Leave request awaiting review', text:fullName(r.employee), action:'leave' })),
  ...(dashboard.value.recent_audit || []).slice(0,2).map(a => ({ icon:'bi-activity', title:a.action || 'Recent activity', text:a.user?.name || 'System', action:'audit' }))
]);

function setActive(key) { active.value = key; sidebarOpen.value = false; error.value=''; }
function flash(message) { notice.value = message; setTimeout(() => notice.value = '', 3500); }
function toggleDark() { darkMode.value = !darkMode.value; localStorage.setItem('bp_dark', darkMode.value ? '1' : '0'); document.documentElement.dataset.theme = darkMode.value ? 'dark' : 'light'; }
function apiGet(url, params = {}) { return api.get(url, { params }).then(r => r.data); }

async function loadAll() {
  loading.value = true; error.value = '';
  try {
    const [d,e,o,l,p] = await Promise.all([
      apiGet('/api/dashboard'), apiGet('/api/employees'), apiGet('/api/organization'),
      apiGet('/api/leave'), apiGet('/api/payroll')
    ]);
    dashboard.value=d; employees.value=e; organization.value=o; leave.value=l; payroll.value=p;
  } catch (e) { error.value=e.response?.data?.message || 'Unable to load HR data.'; }
  finally { loading.value=false; }
}
async function signIn() {
  loading.value=true; error.value='';
  try {
    const response=await api.post('/api/auth/login', login);
    localStorage.setItem('bp_token', response.data.token);
    api.defaults.headers.common.Authorization=`Bearer ${response.data.token}`;
    user.value=response.data.user; await loadAll(); flash('Welcome back to BluePrint HR');
  } catch (e) { error.value=e.response?.data?.message || 'Sign in failed.'; }
  finally { loading.value=false; }
}
async function signOut() {
  try { await api.post('/api/auth/logout'); } finally {
    localStorage.removeItem('bp_token'); delete api.defaults.headers.common.Authorization; user.value=null;
  }
}
function resetEmployeeForm() {
  Object.assign(employeeForm,{employee_no:'',first_name:'',middle_name:'',last_name:'',email:'',phone:'',employment_status:'Active',basic_salary:0,branch_id:null,department_id:null});
}
async function createEmployee() {
  error.value='';
  try { await api.post('/api/employees', employeeForm); showEmployeeModal.value=false; resetEmployeeForm(); await loadAll(); flash('Employee added successfully'); }
  catch(e) { error.value=e.response?.data?.message || 'Could not create employee.'; }
}
async function submitLeave() {
  try { await api.post('/api/leave/requests', leaveForm); showLeaveModal.value=false; Object.assign(leaveForm,{employee_id:null,leave_type_id:null,start_date:'',end_date:'',days_requested:1,reason:''}); await loadAll(); flash('Leave request submitted'); }
  catch(e) { error.value=e.response?.data?.message || 'Could not submit leave request.'; }
}
async function decideLeave(request, status) {
  try { await api.post(`/api/leave/requests/${request.id}/decision`, { status, decision_comment: status === 'Approved' ? 'Approved in HR workspace' : 'Decision recorded in HR workspace' }); await loadAll(); flash(`Leave request ${status.toLowerCase()}`); }
  catch(e) { error.value=e.response?.data?.message || 'Could not update leave request.'; }
}
async function processPayroll(periodId) {
  if (!periodId) return;
  try { await api.post('/api/payroll/process', { payroll_period_id:periodId }); await loadAll(); setActive('payroll'); flash('Payroll processed successfully'); }
  catch(e) { error.value=e.response?.data?.message || 'Could not process payroll.'; }
}
function downloadEmployees() { window.location.href=`${API_URL}/api/reports/employees.xlsx`; }
function downloadPayslip(id) { window.location.href=`${API_URL}/api/reports/payslips/${id}.pdf`; }
function runGlobalSearch() {
  const q=globalSearch.value.trim().toLowerCase();
  if (!q) return;
  if ((employees.value.data || []).some(e => [fullName(e),e.employee_no,e.email].join(' ').toLowerCase().includes(q))) setActive('employees');
  else if ((leave.value.requests || []).some(r => [fullName(r.employee),r.leave_type?.name,r.status].join(' ').toLowerCase().includes(q))) setActive('leave');
  else flash('No matching HR records found');
}

api.interceptors.response.use(r => r, error => {
  if (error.response?.status === 401) { localStorage.removeItem('bp_token'); delete api.defaults.headers.common.Authorization; user.value=null; }
  return Promise.reject(error);
});

onMounted(async () => {
  document.documentElement.dataset.theme = darkMode.value ? 'dark' : 'light';
  try { const response=await apiGet('/api/auth/me'); user.value=response.user; await loadAll(); } catch (_) {}
});
</script>

<template>
  <div v-if="!user" class="login-page">
    <div class="login-orb orb-one"></div><div class="login-orb orb-two"></div>
    <div class="login-panel">
      <div class="login-brand"><div class="brand-mark">BP</div><div><strong>BluePrint HR</strong><span>People • Payroll • Compliance</span></div></div>
      <div class="login-copy"><span class="eyebrow">HR OPERATIONS PLATFORM</span><h1>Run your people operations from one place.</h1><p>Employees, leave, payroll and compliance — designed for fast Kenyan HR teams.</p></div>
      <div v-if="error" class="alert alert-danger border-0">{{ error }}</div>
      <form @submit.prevent="signIn" class="login-form">
        <label>Email<input v-model="login.email" type="email" autocomplete="email" placeholder="you@company.co.ke" required></label>
        <label>Password<input v-model="login.password" type="password" autocomplete="current-password" placeholder="••••••••" required></label>
        <div class="login-options"><label class="check"><input v-model="login.remember" type="checkbox"> Remember me</label><span>Secure token login</span></div>
        <button class="primary-btn w-100" :disabled="loading">{{ loading ? 'Signing in…' : 'Sign in to workspace' }} <i class="bi bi-arrow-right"></i></button>
      </form>
      <div class="login-footer"><i class="bi bi-shield-lock"></i> Protected HR workspace • Tenant isolated</div>
    </div>
  </div>

  <div v-else class="app-shell">
    <aside class="sidebar" :class="{open:sidebarOpen}">
      <div class="sidebar-top">
        <div class="brand"><div class="brand-mark">BP</div><div class="brand-copy"><strong>BluePrint</strong><span>HR platform</span></div></div>
        <button class="mobile-close" @click="sidebarOpen=false"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="workspace-switch"><div class="company-avatar">{{ initials(dashboard.tenant) }}</div><div><strong>{{ dashboard.tenant?.company_name || 'Organization' }}</strong><span>HR workspace</span></div><i class="bi bi-chevron-expand"></i></div>
      <div class="nav-label">WORKSPACE</div>
      <nav>
        <a v-for="item in navItems" :key="item.key" href="#" :class="{active:active===item.key}" @click.prevent="setActive(item.key)"><i class="bi" :class="item.icon"></i><span>{{ item.label }}</span><b v-if="item.key==='leave' && pendingLeave.length">{{ pendingLeave.length }}</b></a>
      </nav>
      <div class="sidebar-bottom">
        <div class="help-card"><i class="bi bi-headset"></i><div><strong>Need help?</strong><span>Open HR support</span></div></div>
        <div class="user-mini"><div class="avatar">{{ initials(user) }}</div><div class="user-meta"><strong>{{ user.name }}</strong><span>{{ role }}</span></div><button @click="signOut" title="Sign out"><i class="bi bi-box-arrow-right"></i></button></div>
      </div>
    </aside>

    <div v-if="sidebarOpen" class="mobile-backdrop" @click="sidebarOpen=false"></div>
    <main class="main">
      <header class="topbar">
        <div class="mobile-menu"><button @click="sidebarOpen=true"><i class="bi bi-list"></i></button></div>
        <div class="search-box"><i class="bi bi-search"></i><input v-model="globalSearch" @keyup.enter="runGlobalSearch" placeholder="Search employees, leave, records…"><kbd>⌘ K</kbd></div>
        <div class="top-actions">
          <button class="icon-btn" @click="toggleDark" :title="darkMode ? 'Light mode' : 'Dark mode'"><i class="bi" :class="darkMode?'bi-sun':'bi-moon-stars'"></i></button>
          <button class="icon-btn notification-btn" @click="showNotifications=!showNotifications"><i class="bi bi-bell"></i><span v-if="notifications.length"></span></button>
          <div class="top-avatar">{{ initials(user) }}</div>
        </div>
        <div v-if="showNotifications" class="notification-popover">
          <div class="popover-head"><strong>Notifications</strong><button @click="showNotifications=false"><i class="bi bi-x"></i></button></div>
          <button v-for="n in notifications" :key="n.title+n.text" class="notification-row" @click="setActive(n.action);showNotifications=false"><i class="bi" :class="n.icon"></i><span><strong>{{ n.title }}</strong><small>{{ n.text }}</small></span></button>
          <div v-if="!notifications.length" class="empty-mini">You're all caught up.</div>
        </div>
      </header>

      <div class="page">
        <div v-if="notice" class="toast-msg"><i class="bi bi-check-circle-fill"></i>{{ notice }}</div>
        <div v-if="error" class="alert alert-danger border-0 shadow-sm">{{ error }} <button class="btn-close float-end" @click="error=''"></button></div>
        <div v-if="loading" class="loading-bar"></div>

        <section v-if="active==='dashboard'">
          <div class="page-heading"><div><span class="eyebrow">OVERVIEW</span><h1>Good to see you, {{ (user.name || 'there').split(' ')[0] }}.</h1><p>Here's what's happening across your workforce today.</p></div><div class="heading-actions"><button v-if="canManagePeople" class="secondary-btn" @click="showEmployeeModal=true"><i class="bi bi-person-plus"></i> Add employee</button><button class="primary-btn" @click="showLeaveModal=true"><i class="bi bi-calendar-plus"></i> Request leave</button></div></div>
          <div class="stats-grid">
            <div class="stat-card"><div class="stat-top"><span>Active employees</span><i class="bi bi-people"></i></div><strong>{{ dashboard.metrics.headcount ?? workforceMix.active }}</strong><small><b>{{ workforceMix.activePct }}%</b> of workforce active</small><div class="mini-progress"><span :style="{width: workforceMix.activePct+'%'}"></span></div></div>
            <div class="stat-card"><div class="stat-top"><span>Monthly payroll</span><i class="bi bi-wallet2"></i></div><strong>{{ money(dashboard.metrics.monthly_payroll) }}</strong><small>Current payroll exposure</small><div class="stat-trend positive"><i class="bi bi-arrow-up-right"></i> Payroll ready</div></div>
            <div class="stat-card"><div class="stat-top"><span>Pending leave</span><i class="bi bi-calendar2-week"></i></div><strong>{{ dashboard.metrics.pending_leave ?? pendingLeave.length }}</strong><small>Requests need attention</small><div class="stat-trend"><i class="bi bi-clock"></i> Review queue</div></div>
            <div class="stat-card"><div class="stat-top"><span>Departments</span><i class="bi bi-diagram-3"></i></div><strong>{{ departmentsCount }}</strong><small>Across organization</small><div class="stat-trend neutral"><i class="bi bi-building"></i> Structured workforce</div></div>
          </div>

          <div class="dashboard-grid">
            <div class="panel large-panel"><div class="panel-head"><div><h2>Workforce snapshot</h2><p>Current employee distribution</p></div><button class="link-btn" @click="setActive('employees')">View people <i class="bi bi-arrow-up-right"></i></button></div>
              <div class="snapshot"><div class="donut" :style="{background:`conic-gradient(var(--accent) ${workforceMix.activePct}%, var(--border) 0)`}"><div><strong>{{ workforceMix.total }}</strong><span>people</span></div></div><div class="legend"><div><span class="dot active-dot"></span><div><strong>{{ workforceMix.active }}</strong><small>Active employees</small></div></div><div><span class="dot inactive-dot"></span><div><strong>{{ workforceMix.inactive }}</strong><small>Inactive / other</small></div></div><div><span class="dot leave-dot"></span><div><strong>{{ pendingLeave.length }}</strong><small>Leave requests</small></div></div></div></div>
            </div>
            <div class="panel"><div class="panel-head"><div><h2>Quick actions</h2><p>Common HR tasks</p></div></div><div class="quick-actions"><button @click="showEmployeeModal=true"><i class="bi bi-person-plus"></i><span>Add employee</span><small>People</small></button><button @click="showLeaveModal=true"><i class="bi bi-calendar-plus"></i><span>Request leave</span><small>Time off</small></button><button @click="setActive('payroll')"><i class="bi bi-calculator"></i><span>Payroll</span><small>Process</small></button><button @click="setActive('reports')"><i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span><small>Export</small></button></div></div>
          </div>

          <div class="dashboard-grid lower"><div class="panel large-panel"><div class="panel-head"><div><h2>Recent employees</h2><p>Latest workforce records</p></div><button class="link-btn" @click="setActive('employees')">All employees</button></div><div class="people-list"><div v-for="e in (dashboard.employees || []).slice(0,5)" :key="e.id" class="person-row"><div class="avatar">{{ initials(e) }}</div><div class="person-main"><strong>{{ fullName(e) }}</strong><span>{{ e.employee_no }} • {{ e.department?.name || 'Unassigned' }}</span></div><span class="status-pill" :class="e.employment_status==='Active'?'success':''">{{ e.employment_status }}</span><strong class="salary">{{ money(e.basic_salary) }}</strong></div><div v-if="!dashboard.employees?.length" class="empty-state">No employee records yet.</div></div></div>
            <div class="panel"><div class="panel-head"><div><h2>Activity</h2><p>Latest audit events</p></div><button class="link-btn" @click="setActive('audit')">View all</button></div><div class="activity-list"><div v-for="a in (dashboard.recent_audit || []).slice(0,5)" :key="a.id" class="activity-row"><span class="activity-icon"><i class="bi bi-activity"></i></span><div><strong>{{ a.action || 'Activity' }}</strong><span>{{ a.user?.name || 'System' }}</span></div><small>{{ a.created_at }}</small></div><div v-if="!dashboard.recent_audit?.length" class="empty-state">No recent activity.</div></div></div>
          </div>
        </section>

        <section v-else-if="active==='employees'">
          <div class="page-heading"><div><span class="eyebrow">PEOPLE</span><h1>Employee directory</h1><p>Manage profiles, statutory identifiers, salary and workforce status.</p></div><div class="heading-actions"><button class="secondary-btn" @click="downloadEmployees"><i class="bi bi-file-earmark-excel"></i> Export Excel</button><button v-if="canManagePeople" class="primary-btn" @click="showEmployeeModal=true"><i class="bi bi-person-plus"></i> Add employee</button></div></div>
          <div class="toolbar"><div class="inline-search"><i class="bi bi-search"></i><input v-model="employeeSearch" placeholder="Search by name, number, email or department"></div><select v-model="employeeStatus"><option>All</option><option>Active</option><option>Inactive</option><option>On Leave</option></select><span class="record-count">{{ filteredEmployees.length }} records</span></div>
          <div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Contact</th><th>Department</th><th>Status</th><th class="right">Basic salary</th></tr></thead><tbody><tr v-for="e in filteredEmployees" :key="e.id"><td><div class="table-person"><div class="avatar">{{ initials(e) }}</div><div><strong>{{ fullName(e) }}</strong><small>{{ e.employee_no }}</small></div></div></td><td><strong>{{ e.email || '—' }}</strong><small>{{ e.phone || 'No phone' }}</small></td><td>{{ e.department?.name || '—' }}</td><td><span class="status-pill" :class="e.employment_status==='Active'?'success':''">{{ e.employment_status }}</span></td><td class="right salary">{{ money(e.basic_salary) }}</td></tr><tr v-if="!filteredEmployees.length"><td colspan="5"><div class="empty-state">No employees match your filters.</div></td></tr></tbody></table></div></div>
        </section>

        <section v-else-if="active==='leave'">
          <div class="page-heading"><div><span class="eyebrow">TIME OFF</span><h1>Leave management</h1><p>Review requests, balances and approvals in one queue.</p></div><button class="primary-btn" @click="showLeaveModal=true"><i class="bi bi-calendar-plus"></i> Request leave</button></div>
          <div class="stats-grid compact"><div class="stat-card"><span>Pending</span><strong>{{ pendingLeave.length }}</strong><small>Needs review</small></div><div class="stat-card"><span>Approved</span><strong>{{ leave.requests.filter(r=>r.status==='Approved').length }}</strong><small>This loaded period</small></div><div class="stat-card"><span>Rejected</span><strong>{{ leave.requests.filter(r=>r.status==='Rejected').length }}</strong><small>This loaded period</small></div></div>
          <div class="toolbar"><div class="inline-search"><i class="bi bi-search"></i><input v-model="globalSearch" placeholder="Search requests"></div><select v-model="leaveStatus"><option>All</option><option>Pending</option><option>Approved</option><option>Rejected</option></select></div>
          <div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Leave type</th><th>Dates</th><th>Days</th><th>Status</th><th class="right">Actions</th></tr></thead><tbody><tr v-for="r in filteredLeave" :key="r.id"><td><div class="table-person"><div class="avatar">{{ initials(r.employee) }}</div><strong>{{ fullName(r.employee) }}</strong></div></td><td>{{ r.leave_type?.name || '—' }}</td><td>{{ r.start_date }} – {{ r.end_date }}</td><td>{{ r.days_requested }}</td><td><span class="status-pill" :class="{success:r.status==='Approved',danger:r.status==='Rejected',warning:r.status==='Pending'}">{{ r.status }}</span></td><td class="right"><template v-if="r.status==='Pending' && canManagePeople"><button class="action-btn approve" @click="decideLeave(r,'Approved')"><i class="bi bi-check"></i></button><button class="action-btn reject" @click="decideLeave(r,'Rejected')"><i class="bi bi-x"></i></button></template></td></tr><tr v-if="!filteredLeave.length"><td colspan="6"><div class="empty-state">No leave requests match your filters.</div></td></tr></tbody></table></div></div>
        </section>

        <section v-else-if="active==='payroll'">
          <div class="page-heading"><div><span class="eyebrow">PAYROLL</span><h1>Payroll command center</h1><p>Kenyan PAYE, NSSF, SHIF and housing levy calculations.</p></div><select v-if="canProcessPayroll" class="period-select" @change="processPayroll($event.target.value)"><option value="">Process a period…</option><option v-for="period in payroll.periods" :key="period.id" :value="period.id">{{ period.name }} • {{ period.status }}</option></select></div>
          <div class="stats-grid"><div class="stat-card"><span>Gross payroll</span><strong>{{ money(payrollTotal) }}</strong><small>Loaded transactions</small></div><div class="stat-card"><span>Net payroll</span><strong>{{ money(netPayroll) }}</strong><small>After statutory deductions</small></div><div class="stat-card"><span>Transactions</span><strong>{{ payroll.transactions.length }}</strong><small>Processed records</small></div><div class="stat-card"><span>Open periods</span><strong>{{ payroll.periods.filter(p=>p.status==='Open').length }}</strong><small>Ready to process</small></div></div>
          <div class="panel table-panel"><div class="panel-head"><div><h2>Payroll transactions</h2><p>Download individual payslips from the table.</p></div></div><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Period</th><th>Gross</th><th>PAYE</th><th>NSSF</th><th>SHIF</th><th>Net pay</th><th></th></tr></thead><tbody><tr v-for="t in payroll.transactions" :key="t.id"><td><strong>{{ fullName(t.employee) }}</strong></td><td>{{ t.period?.name }}</td><td>{{ money(t.gross_pay) }}</td><td>{{ money(t.paye) }}</td><td>{{ money(t.nssf) }}</td><td>{{ money(t.shif) }}</td><td class="salary">{{ money(t.net_pay) }}</td><td class="right"><button class="action-btn" @click="downloadPayslip(t.id)"><i class="bi bi-file-earmark-pdf"></i></button></td></tr><tr v-if="!payroll.transactions.length"><td colspan="8"><div class="empty-state">No payroll transactions yet. Process an open period to calculate payroll.</div></td></tr></tbody></table></div></div>
        </section>

        <section v-else-if="active==='organization'">
          <div class="page-heading"><div><span class="eyebrow">STRUCTURE</span><h1>Organization</h1><p>Manage the structure that powers workforce reporting.</p></div></div>
          <div class="dashboard-grid"><div class="panel"><div class="panel-head"><div><h2>Branches</h2><p>{{ organization.branches.length }} locations</p></div></div><div class="org-list"><div v-for="b in organization.branches" :key="b.id"><span class="org-icon"><i class="bi bi-building"></i></span><strong>{{ b.name }}</strong><small>{{ b.code }}</small></div><div v-if="!organization.branches.length" class="empty-state">No branches configured.</div></div></div><div class="panel"><div class="panel-head"><div><h2>Departments</h2><p>{{ organization.departments.length }} teams</p></div></div><div class="org-list"><div v-for="d in organization.departments" :key="d.id"><span class="org-icon"><i class="bi bi-diagram-3"></i></span><strong>{{ d.name }}</strong><small>{{ d.code }}</small></div><div v-if="!organization.departments.length" class="empty-state">No departments configured.</div></div></div></div>
          <div class="panel mt-4"><div class="panel-head"><div><h2>Workforce configuration</h2><p>Reusable HR master data</p></div></div><div class="config-grid"><div><strong>{{ organization.designations.length }}</strong><span>Designations</span></div><div><strong>{{ organization.grades.length }}</strong><span>Grades</span></div><div><strong>{{ organization.employment_types.length }}</strong><span>Employment types</span></div></div></div>
        </section>

        <section v-else-if="active==='ess'">
          <div class="page-heading"><div><span class="eyebrow">MY WORKSPACE</span><h1>Employee self-service</h1><p>Your profile, leave balances and personal HR information.</p></div></div>
          <div class="dashboard-grid"><div class="panel profile-panel"><div class="profile-cover"></div><div class="profile-body"><div class="profile-avatar">{{ initials(user.employee || user) }}</div><h2>{{ fullName(user.employee) || user.name }}</h2><p>{{ user.employee?.employee_no || user.email }}</p><div class="profile-details"><div><span>Department</span><strong>{{ user.employee?.department?.name || '—' }}</strong></div><div><span>Email</span><strong>{{ user.email }}</strong></div><div><span>Phone</span><strong>{{ user.employee?.phone || user.phone || '—' }}</strong></div></div></div></div><div class="panel"><div class="panel-head"><div><h2>Leave balances</h2><p>Available time off</p></div><button class="link-btn" @click="setActive('leave')">Manage</button></div><div class="balance-list"><div v-for="b in leave.balances" :key="b.id"><span>{{ b.leave_type?.name }}</span><strong>{{ Number(b.available_days).toFixed(2) }} days</strong><div class="balance-bar"><span :style="{width:Math.min(100, Number(b.available_days)/Math.max(1,Number(b.allocated_days))*100)+'%'}"></span></div></div><div v-if="!leave.balances.length" class="empty-state">No leave balances found.</div></div></div></div>
        </section>

        <section v-else-if="active==='reports'">
          <div class="page-heading"><div><span class="eyebrow">REPORTING</span><h1>Reports & exports</h1><p>Download operational reports without leaving the HR workspace.</p></div></div>
          <div class="report-grid"><button class="report-card" @click="downloadEmployees"><i class="bi bi-file-earmark-spreadsheet"></i><strong>Employee master</strong><span>Excel export of workforce records</span><b>Download <i class="bi bi-arrow-right"></i></b></button><button class="report-card" @click="setActive('payroll')"><i class="bi bi-receipt"></i><strong>Payroll & payslips</strong><span>Review transactions and download payslips</span><b>Open payroll <i class="bi bi-arrow-right"></i></b></button><button class="report-card" @click="setActive('audit')"><i class="bi bi-shield-check"></i><strong>Audit activity</strong><span>Review tenant operational history</span><b>View audit trail <i class="bi bi-arrow-right"></i></b></button></div>
        </section>

        <section v-else-if="active==='audit'">
          <div class="page-heading"><div><span class="eyebrow">COMPLIANCE</span><h1>Audit trail</h1><p>Tenant-scoped operational history for accountability.</p></div></div>
          <div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead><tbody><tr v-for="a in dashboard.recent_audit" :key="a.id"><td>{{ a.created_at }}</td><td>{{ a.user?.name || 'System' }}</td><td><span class="status-pill">{{ a.action }}</span></td><td>{{ a.entity_type }} #{{ a.entity_id }}</td><td>{{ a.details || 'Record changed' }}</td></tr><tr v-if="!dashboard.recent_audit.length"><td colspan="5"><div class="empty-state">No audit records found.</div></td></tr></tbody></table></div></div>
        </section>
      </div>
    </main>
  </div>

  <div v-if="showEmployeeModal" class="modal-backdrop-custom" @click.self="showEmployeeModal=false"><div class="modal-card-custom"><div class="modal-head"><div><span class="eyebrow">PEOPLE</span><h2>Register employee</h2></div><button @click="showEmployeeModal=false"><i class="bi bi-x-lg"></i></button></div><form @submit.prevent="createEmployee"><div class="form-grid"><label>Employee number<input v-model="employeeForm.employee_no" required></label><label>First name<input v-model="employeeForm.first_name" required></label><label>Middle name<input v-model="employeeForm.middle_name"></label><label>Last name<input v-model="employeeForm.last_name" required></label><label>Email<input v-model="employeeForm.email" type="email"></label><label>Phone<input v-model="employeeForm.phone"></label><label>Basic salary (KES)<input v-model="employeeForm.basic_salary" type="number" min="0" step="0.01" required></label><label>Status<select v-model="employeeForm.employment_status"><option>Active</option><option>Inactive</option><option>On Leave</option></select></label><label>Branch<select v-model="employeeForm.branch_id"><option :value="null">Select branch</option><option v-for="b in organization.branches" :value="b.id" :key="b.id">{{ b.name }}</option></select></label><label>Department<select v-model="employeeForm.department_id"><option :value="null">Select department</option><option v-for="d in organization.departments" :value="d.id" :key="d.id">{{ d.name }}</option></select></label></div><div class="modal-actions"><button type="button" class="secondary-btn" @click="showEmployeeModal=false">Cancel</button><button class="primary-btn">Save employee</button></div></form></div></div>
  <div v-if="showLeaveModal" class="modal-backdrop-custom" @click.self="showLeaveModal=false"><div class="modal-card-custom small"><div class="modal-head"><div><span class="eyebrow">TIME OFF</span><h2>Request leave</h2></div><button @click="showLeaveModal=false"><i class="bi bi-x-lg"></i></button></div><form @submit.prevent="submitLeave"><div v-if="!isEmployee" class="form-grid one"><label>Employee ID<input v-model="leaveForm.employee_id" type="number"></label></div><div class="form-grid one"><label>Leave type<select v-model="leaveForm.leave_type_id" required><option :value="null">Select type</option><option v-for="t in leave.types" :key="t.id" :value="t.id">{{ t.name }}</option></select></label><label>Start date<input v-model="leaveForm.start_date" type="date" required></label><label>End date<input v-model="leaveForm.end_date" type="date" required></label><label>Days requested<input v-model="leaveForm.days_requested" type="number" min="0.5" step="0.5" required></label><label>Reason<textarea v-model="leaveForm.reason" rows="4"></textarea></label></div><div class="modal-actions"><button type="button" class="secondary-btn" @click="showLeaveModal=false">Cancel</button><button class="primary-btn">Submit request</button></div></form></div></div>
</template>
