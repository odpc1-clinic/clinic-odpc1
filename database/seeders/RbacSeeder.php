<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Ask for db migration refresh, default is no
        if ($this->command->confirm('Do you wish to refresh migration before seeding, it will clear all old data ?')) {
            // Call the php artisan migrate:refresh
            $this->command->call('migrate:refresh');
            $this->command->warn("Data cleared, starting from blank database.");
        }

        // Seed the default permissions
        $permissions = Permission::defaultPermissions();

        foreach ($permissions as $perms) {
            Permission::firstOrCreate(['name' => $perms]);
        }

        $this->command->info('Default Permissions added.');

        // Confirm roles needed
        if ($this->command->confirm('Create Roles for user, default is admin and user? [y|N]', true)) {

            // Ask for roles from input
            $input_roles = $this->command->ask('Enter roles in comma separate format.', 'Admin,Doctor,Nurse,Registrar,Lab,Pharmacy');

            // Explode roles
            $roles_array = explode(',', $input_roles);

            // add roles
            foreach($roles_array as $role) {
                $role = Role::firstOrCreate(['name' => trim($role)]);

                if( $role->name == 'Admin' ) {
                    // assign all permissions
                    $role->syncPermissions(Permission::all());
                    $this->command->info('Admin granted all the permissions');
                } else {
                    // for others by default only read access
                    $role->syncPermissions(Permission::where('name', 'LIKE', 'view_%')->get());
                }

                // create one user for each role
                $this->createUser($role);
            }

            $this->command->info('Roles ' . $input_roles . ' added successfully');

        } else {
            Role::firstOrCreate(['name' => 'User']);
            $this->command->info('Added only default user role.');
        }

        // now lets seed some posts for demo
//        factory(\App\Post::class, 30)->create();
//        $this->command->info('Some Posts data seeded.');
        $this->command->warn('All done :)');
    }

    /**
     * Create a user with given role
     *
     * @param $role
     */
    private function createUser($role)
    {


        if( $role->name == 'Admin' ) {
            $user = User::create([
                'name' => 'Admin admin',
                'email' => 'admin@admin.com',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role->name);
        }elseif ($role->name == 'Doctor' ){
            $user = User::create([
                'name' => 'Doctor doctor',
                'email' => 'doctor@doctor.com',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role->name);
        }elseif ($role->name == 'Nurse' ){
            $user = User::create([
                'name' => 'Nurse nurse',
                'email' => 'nurse@nurse.com',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role->name);
        }elseif ($role->name == 'Registrar' ){
            $user = User::create([
                'name' => 'Registrar registrar',
                'email' => 'registrar@registrar.com',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role->name);
        }elseif ($role->name == 'Lab' ){
            $user = User::create([
                'name' => 'Lab lab',
                'email' => 'lab@lab.com',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role->name);

        }elseif ($role->name == 'Pharmacy' ){
            $user = User::create([
                'name' => 'Pharmacy pharmacy',
                'email' => 'pharmacy@pharmacy.com',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role->name);
        }
    }
}
