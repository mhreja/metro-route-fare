<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Station;
use App\Models\Distance;
use Illuminate\Support\Carbon;
use SplPriorityQueue;

class MetroRouteFareController extends Controller
{
    public function getMetroRouteFare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_station'   => ['required', 'numeric', 'exists:stations,id'],
            'end_station'     => ['required', 'numeric', 'exists:stations,id']
        ]);

        if($validator->fails()) {
            return response()->json([
                'success'       =>  false,
                'message'       =>  $validator->errors()->first()
            ], 422);
        }

        try {
            $startStationId = $request->start_station;
            $endStationId = $request->end_station;

            // Get all stations from the database
            $stations = Station::all();

            // Create an array to store the times and distances to each station
            $times = $distances = [];
            $endStationStoppageTime = 00;

            // Get start and end station name
            $startingStationName = $endStationName = '';

            // Initialize all distances as infinite except the start station
            foreach ($stations as $station) {
                if($station->id == $startStationId){
                    $startingStationName = $station->name;
                }
                if($station->id == $endStationId){
                    $endStationName = $station->name;
                    $endStationStoppageTime = $station->stoppage_time;
                }

                $times[$station->id] = $station->id == $startStationId ? 0 : INF;
                $distances[$station->id] = $station->id == $startStationId ? 0 : INF;
            }

            // Create an array to store the previous station for each station
            $previous = [];

            // Create a priority queue to store the unvisited stations
            $unvisited = new SplPriorityQueue();
            $unvisited->insert($startStationId, 0);

            // Loop until all stations are visited
            while (!$unvisited->isEmpty()) {
                // Get the station with the smallest distance
                $currentStationId = $unvisited->extract();

                // Break the loop if we reached the destination station
                if ($currentStationId == $endStationId) {
                    break;
                }

                // Get the current station and its neighbors
                $currentStation = Station::find($currentStationId);
                $neighbors = $currentStation->connectedStations;

                foreach ($neighbors as $neighbor) {
                    // Calculate the time and distance from the current station to the neighbor
                    $timeToNeighbor = $times[$currentStationId] + $neighbor->pivot->travel_time + $neighbor->stoppage_time;
                    $distanceToNeighbor = $distances[$currentStationId] + $neighbor->pivot->distance;

                    // If the new distance is smaller, update the distance and previous station
                    if ($timeToNeighbor < $times[$neighbor->id]) {
                        $times[$neighbor->id] = $timeToNeighbor;
                        $distances[$neighbor->id] = $distanceToNeighbor;
                        $previous[$neighbor->id] = $currentStationId;

                        // Insert the neighbor into the priority queue
                        $unvisited->insert($neighbor->id, -$timeToNeighbor);
                    }
                }
            }


            // Build the shortest path by backtracking from the destination station
            $path = [];
            $currentStationId = $endStationId;

            while (isset($previous[$currentStationId])) {
                array_unshift($path, $currentStationId);
                $currentStationId = $previous[$currentStationId];
            }

            // Add the start station to the path
            array_unshift($path, $startStationId);


            // Get stations on the route/path
            $stationsOnPath  = Station::whereIn('id', $path)
            ->orderByRaw("FIELD(id, " . implode(',', $path) . ")")
            ->get();


            // Get interchange Stations
            $interchangeStations  = $stationsOnPath->filter(function ($station, $key) use ($stationsOnPath) {
                if ($key < $stationsOnPath->count() - 1) {
                    $nextStation = $stationsOnPath[$key + 1];
                    $stationLines = $station->lines;
                    $nextStationLines = $nextStation->lines;
                    sort($stationLines);
                    sort($nextStationLines);
                    if($key !== 0){
                        $previousStation = $stationsOnPath[$key - 1];
                        $previousStationLines = $previousStation->lines;
                        sort($previousStationLines);
                        return ($stationLines !== $nextStationLines) && ($previousStationLines !== $nextStationLines) && (count($stationLines) > count($nextStationLines));
                    } else{
                        return ($stationLines !== $nextStationLines) && (count($stationLines) > count($nextStationLines));
                    }
                }
                return false;
            });

            // Get linesInterChange
            $linesInerchange = [];
            foreach($interchangeStations as $s){
                $linesInerchange = array_merge($linesInerchange, $s->lines);
            }

            // Return the shortest path and its distance
            $response = [
                'success' => true,
                'linesInterchange' => array_unique($linesInerchange),
                'interchangeStations' => $interchangeStations->pluck('name')->toArray(),
                'startingStationName' => $startingStationName,
                'path' => $stationsOnPath->pluck('name')->toArray(),
                'endStationName' => $endStationName,
                'timeBetweenStations' => ($times[$endStationId] - $endStationStoppageTime),
                // 'distanceBetweenStations' => $distances[$endStationId],
                'totalFare' => $this->getFare($startStationId, $endStationId, $distances[$endStationId])
            ];

            return response()->json($response, 200);
        } catch(\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getFare($startStationId, $endStationId, $distance)
    {
        $startStation = Station::find($startStationId);
        $endStation = Station::find($endStationId);

        $fare = 0;
        $dayOfWeek = Carbon::now()->format('l');

        if(in_array('rapid', $startStation->lines) && in_array('rapid', $endStation->lines)){
            $fare = 20;
        } else{
            if ($distance >= 0 && $distance <= 2000) {
                $fare = ($dayOfWeek === 'Sunday' || $this->isPublicHoliday()) ? 10 : 10;
            } elseif ($distance > 2000 && $distance <= 5000) {
                $fare = ($dayOfWeek === 'Sunday' || $this->isPublicHoliday()) ? 10 : 20;
            } elseif ($distance > 5000 && $distance <= 12000) {
                $fare = ($dayOfWeek === 'Sunday' || $this->isPublicHoliday()) ? 20 : 30;
            } elseif ($distance > 12000 && $distance <= 21000) {
                $fare = ($dayOfWeek === 'Sunday' || $this->isPublicHoliday()) ? 30 : 40;
            } elseif ($distance > 21000 && $distance <= 32000) {
                $fare = ($dayOfWeek === 'Sunday' || $this->isPublicHoliday()) ? 40 : 50;
            } elseif ($distance > 32000) {
                $fare = ($dayOfWeek === 'Sunday' || $this->isPublicHoliday()) ? 50 : 60;
            }
        }

        return $fare;
    }



    public function isPublicHoliday()
    {
        // Get the current date
        $currentDate = Carbon::now();

        // Check if the current date is a public holiday in India
        // You can customize this list based on your specific public holidays
        $publicHolidays = [
            '01-01',  // New Year's Day
            '26-01',  // Republic Day
            '15-08',  // Independence Day
            '02-10',  // Gandhi Jayanti
            '25-12',  // Christmas Day
        ];

        $formattedDate = $currentDate->format('d-m');

        $isPublicHoliday = in_array($formattedDate, $publicHolidays);

        return $isPublicHoliday;
    }

}
