<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Seeder;


// composer dump-autoload
// php artisan migrate:fresh --seed

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(ThaiAddressTablesSeeder::class);
        // User::factory(10)->create();
        $this->call(RbacSeeder::class);
        $this->call(MedicineSeeder::class);
    }
}
