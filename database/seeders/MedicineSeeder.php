<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('medicines')->insert([
            'name' => 'Benzathine Penicillin G',
            'dose' => '2.4 million units',
            'route' => 'IM stat',
            'amount' => '1 ครั้ง',
        ]);

        DB::table('medicines')->insert([
            'name' => 'BCeftriaxone',
            'dose' => '500 mg',
            'route' => 'IM stat',
            'amount' => '1 ครั้ง',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Acyclovir(400mg)',
            'dose' => '1*3',
            'route' => 'Oral pc',
            'amount' => '5-7days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Azithromycin(250mg)',
            'dose' => '4 caps',
            'route' => 'Oral pc',
            'amount' => '1 ครั้ง',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Cefixime(100mg)',
            'dose' => '4 caps',
            'route' => 'Oral pc',
            'amount' => '1 ครั้ง',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Fluconazole(150mg)',
            'dose' => '1 caps',
            'route' => 'Oral pc',
            'amount' => '1 ครั้ง',
        ]);


        DB::table('medicines')->insert([
            'name' => 'Metronidazole(200mg)',
            'dose' => '2*3',
            'route' => 'Oral pc',
            'amount' => '7days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Tinidazole(500mg)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);


        //-----------------------------------------------------
        DB::table('medicines')->insert([
            'name' => 'Acyclovir cream',
            'dose' => '5 time/day',
            'route' => 'apply',
            'amount' => '5-7days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Clotrimazole cream',
            'dose' => '',
            'route' => 'apply',
            'amount' => '7-14 days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Clotrimazole(100mg)',
            'dose' => '2 tabs',
            'route' => 'Trans vg hs',
            'amount' => '3 days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Terramycin eye ointment',
            'dose' => '',
            'route' => 'apply',
            'amount' => '1 หลอด',
        ]);
        //---------------------------------------------------------
        DB::table('medicines')->insert([
            'name' => 'adrenaline',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'chlopheniramine',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Dexamethasone',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Amoxycillin(500mg)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Amoxycillin(875)+clavulanic(125)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Clarithromycin(500)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Clotrimoxazole400+800mg',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'icloxacillin(250mg)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Hydroxyzine(10mg)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);


        DB::table('medicines')->insert([
            'name' => 'Almagel',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);

        DB::table('medicines')->insert([
            'name' => 'calminative',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Mefenamic acid(250)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Diazepam(2mg)',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);


        DB::table('medicines')->insert([
            'name' => 'Vit B6',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Benzyl benzoate solution',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'Methyl salicylate cream',
            'dose' => '',
            'route' => '',
            'amount' => '',
        ]);
        //-------------------------


        DB::table('medicines')->insert([
            'name' => 'Doxycycline(100mg)',
            'dose' => '1*2',
            'route' => 'Oral pc', 
            'amount' => '14 days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Norfloxacin(400mg)',
            'dose' => '1*2',
            'route' => 'Oral pc',
            'amount' => '3 days',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Chlopheniramine(4mg)',
            'dose' => '1*3',
            'route' => 'Oral pc',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Loratadine(10mg)',
            'dose' => '1*1',
            'route' => 'Oral pc',
            'amount' => '10 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Bromhexine(8mg)',
            'dose' => '1*3',
            'route' => 'Oral pc',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Dextrometrophan(15mg)',
            'dose' => '1*4',
            'route' => 'Oral pc',
            'amount' => '20 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Brown mixture',
            'dose' => '1*4',
            'route' => 'Oral pc,hs',
            'amount' => '20 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Brown mixture',
            'dose' => '1*1', 
            'route' => 'Oral pc',
            'amount' => '1 ขวด',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Hyoscin(10mg)',
            'dose' => '1*3',
            'route' => 'Oral pc',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Omeprazole(20mg)',
            'dose' => '1*1',
            'route' => 'Oral ac',
            'amount' => '10 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Domperidone(10mg)',
            'dose' => '1*31',
            'route' => 'Oral pc',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Diclofenac(25mg)',
            'dose' => '1 prn q 8hr',
            'route' => 'Oral pc',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Ibuprofen(400mg)',
            'dose' => '1 prn q 8hr',
            'route' => 'Oral pc',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Paracetamol(500)',
            'dose' => '1 prn q 8hr',
            'route' => 'Oral',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'Dimenhydrinate(50mg)',
            'dose' => '1 prn q 6hr',
            'route' => 'Oral',
            'amount' => '15 tabs',
        ]);

        DB::table('medicines')->insert([
            'name' => 'TA oral paste',
            'dose' => '',
            'route' => 'apply',
            'amount' => '1 หลอด',
        ]);
        DB::table('medicines')->insert([
            'name' => 'TA cream 0.1%',
            'dose' => '',
            'route' => 'apply',
            'amount' => '1 หลอด',
        ]);
        DB::table('medicines')->insert([
            'name' => 'podophylline',
            'dose' => '',
            'route' => 'apply',
            'amount' => '',
        ]);
        DB::table('medicines')->insert([
            'name' => 'TCA',
            'dose' => '',
            'route' => 'apply',
            'amount' => '',
        ]);
    }
}
