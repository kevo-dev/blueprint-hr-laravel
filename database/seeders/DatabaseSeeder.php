<?php
namespace Database\Seeders;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Grade;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\NssfRate;
use App\Models\ShifRate;
use App\Models\TaxBracket;
use App\Models\TaxRelief;
use App\Models\Tenant;
use App\Models\User;
use App\Models\HousingLevyRate;
use App\Models\PayrollPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant=Tenant::firstOrCreate(['subdomain'=>'blueprint'],['company_name'=>'BluePrint Kenya Ltd','kra_pin'=>'P051234567X','email'=>'info@blueprint.co.ke','phone'=>'+254 712 345 678','address'=>'Delta Towers, Westlands, Nairobi','status'=>'Active','currency'=>'KES','timezone'=>'Africa/Nairobi']);
        $branch=Branch::firstOrCreate(['tenant_id'=>$tenant->id,'code'=>'HQ'],['name'=>'Nairobi Headquarters','location'=>'Westlands, Nairobi']);
        $departments=collect([['Human Resources','HR'],['Finance & Payroll','FIN'],['Engineering','ENG']])->mapWithKeys(fn($v)=>[$v[0]=>Department::firstOrCreate(['tenant_id'=>$tenant->id,'code'=>$v[1]],['name'=>$v[0],'branch_id'=>$branch->id])]);
        $designations=collect(['HR Manager','Payroll Accountant','Senior Software Engineer'])->mapWithKeys(fn($name)=>[$name=>Designation::firstOrCreate(['tenant_id'=>$tenant->id,'name'=>$name],['description'=>$name.' responsibilities'])]);
        $grades=collect([['Grade A - Executive','A',150000,300000],['Grade B - Management','B',90000,149000],['Grade C - Staff','C',45000,89000]])->mapWithKeys(fn($v)=>[$v[0]=>Grade::firstOrCreate(['tenant_id'=>$tenant->id,'name'=>$v[0]],['level'=>$v[1],'min_salary'=>$v[2],'max_salary'=>$v[3]])]);
        $types=collect(['Permanent','Contract','Intern'])->mapWithKeys(fn($name)=>[$name=>EmploymentType::firstOrCreate(['tenant_id'=>$tenant->id,'name'=>$name],['description'=>$name.' employment'])]);
        $employees=[['George','Wamola','Kenyatta','EMP-2026-001','george.wamola@blueprint.co.ke',180000,'HR Manager','Grade A - Executive','Human Resources'],['Amina','Wanjiku','Odhiambo','EMP-2026-002','amina.odhiambo@blueprint.co.ke',125000,'Payroll Accountant','Grade B - Management','Finance & Payroll']];
        foreach($employees as $row){ Employee::firstOrCreate(['tenant_id'=>$tenant->id,'employee_no'=>$row[3]],['payroll_no'=>'PAY-'.substr($row[3],-3),'first_name'=>$row[0],'middle_name'=>$row[1],'last_name'=>$row[2],'gender'=>$row[2]==='Kenyatta'?'Male':'Female','dob'=>$row[2]==='Kenyatta'?'1990-05-12':'1994-08-22','id_no'=>$row[3].'-ID','kra_pin'=>'A'.substr(md5($row[3]),0,9).'Y','nssf_no'=>'NSSF'.substr(md5($row[3]),0,6),'shif_no'=>'SHIF'.substr(md5($row[3]),0,6),'phone'=>'+254 700 000 000','email'=>$row[4],'branch_id'=>$branch->id,'department_id'=>$departments[$row[8]]->id,'designation_id'=>$designations[$row[6]]->id,'grade_id'=>$grades[$row[7]]->id,'employment_type_id'=>$types['Permanent']->id,'employment_date'=>'2023-01-15','employment_status'=>'Active','basic_salary'=>$row[5],'bank_name'=>'Equity Bank','bank_branch'=>'Westlands','account_number'=>'DEMO-'.$row[3]]); }
        $demoPassword=env('BLUEPRINT_DEMO_PASSWORD');
        if (!$demoPassword) throw new RuntimeException('Set BLUEPRINT_DEMO_PASSWORD before running the development seeder.');
        $admin=User::firstOrCreate(['email'=>env('BLUEPRINT_ADMIN_EMAIL','admin@blueprint.test')],['name'=>'BluePrint HR Administrator','password'=>$demoPassword]);
        $admin->forceFill(['tenant_id'=>$tenant->id,'role'=>Role::CompanyAdmin->value,'must_change_password'=>true])->save();
        $manager=User::firstOrCreate(['email'=>'hr.manager@blueprint.test'],['name'=>'HR Manager','password'=>$demoPassword]);
        $manager->forceFill(['tenant_id'=>$tenant->id,'role'=>Role::HRManager->value,'must_change_password'=>true])->save();
        $employee=Employee::where('tenant_id',$tenant->id)->where('email','george.wamola@blueprint.co.ke')->first(); if($employee){$employeeUser=$employee->user()->updateOrCreate(['email'=>$employee->email],['name'=>$employee->full_name,'password'=>$demoPassword]); $employeeUser->forceFill(['tenant_id'=>$tenant->id,'role'=>Role::Employee->value,'employee_id'=>$employee->id,'must_change_password'=>true])->save();}
        if(!TaxBracket::where('tenant_id',$tenant->id)->exists()){ foreach([[1,0,24000,.10],[2,24001,32333,.25],[3,32334,500000,.30],[4,500001,800000,.325],[5,800001,null,.35]] as $r) TaxBracket::create(['tenant_id'=>$tenant->id,'band_order'=>$r[0],'lower_limit'=>$r[1],'upper_limit'=>$r[2],'rate'=>$r[3]]); TaxRelief::create(['tenant_id'=>$tenant->id,'relief_name'=>'Personal Relief','monthly_amount'=>2400]); TaxRelief::create(['tenant_id'=>$tenant->id,'relief_name'=>'Insurance Relief','monthly_amount'=>240]); NssfRate::create(['tenant_id'=>$tenant->id,'tier_name'=>'Tier I','lower_limit'=>0,'upper_limit'=>8000,'employee_rate'=>.06,'employer_rate'=>.06]); NssfRate::create(['tenant_id'=>$tenant->id,'tier_name'=>'Tier II','lower_limit'=>8001,'upper_limit'=>72000,'employee_rate'=>.06,'employer_rate'=>.06]); ShifRate::create(['tenant_id'=>$tenant->id,'percentage'=>.0275,'min_amount'=>300]); HousingLevyRate::create(['tenant_id'=>$tenant->id,'employee_percentage'=>.015,'employer_percentage'=>.015]); }
        foreach([['Annual Leave',21,'Yes'],['Sick Leave',14,'Yes'],['Compassionate Leave',5,'Yes'],['Maternity Leave',90,'Yes'],['Paternity Leave',14,'Yes'],['Study Leave',10,'No']] as $leave){LeaveType::firstOrCreate(['tenant_id'=>$tenant->id,'name'=>$leave[0]],['default_days'=>$leave[1],'paid'=>$leave[2],'description'=>$leave[0]]);}
        foreach(Employee::where('tenant_id',$tenant->id)->get() as $emp) foreach(LeaveType::where('tenant_id',$tenant->id)->get() as $type) LeaveBalance::firstOrCreate(['employee_id'=>$emp->id,'leave_type_id'=>$type->id,'year'=>now()->year],['tenant_id'=>$tenant->id,'allocated_days'=>$type->default_days,'used_days'=>0,'carried_forward'=>0]);
        PayrollPeriod::firstOrCreate(['tenant_id'=>$tenant->id,'month'=>now()->month,'year'=>now()->year],['name'=>now()->format('F Y'),'status'=>'Open']);
    }
}
