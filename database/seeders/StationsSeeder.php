<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvFile = fopen(base_path("database/data/stations.csv"), "r");

        $firstline = true; //if has header then set true
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                Station::firstOrCreate(
                    ["name" => $data['0']],
                    ["lines" => explode(',', $data[1]), "stoppage_time" => $data[2]]
                );
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
