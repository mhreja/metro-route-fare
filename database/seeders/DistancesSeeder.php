<?php

namespace Database\Seeders;

use App\Models\Distance;
use App\Models\Station;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DistancesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvFile = fopen(base_path("database/data/distances.csv"), "r");

        $firstline = true; //if has header then set true
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                $from = Station::where('name', $data[0])->first();
                $to = Station::where('name', $data[1])->first();
                if($from && $to){
                    // Up Train
                    Distance::create([
                        "from" => $from->id,
                        "to" => $to->id,
                        "distance" => $data[2],
                        "travel_time" => $data[3]
                    ]);
                    // Down train
                    Distance::create([
                        "from" => $to->id,
                        "to" => $from->id,
                        "distance" => $data[2],
                        "travel_time" => $data[3]
                    ]);
                } else{
                    Log::error('Distances seeding error: from- ' . $data[0] . ' or to- ' . $data[1] . 'not found!' );
                }
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
