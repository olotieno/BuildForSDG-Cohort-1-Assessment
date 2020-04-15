<?php
$time1 = microtime(true);
// required headers
header("Access-Control-Allow-Origin: *");
//header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

//Make sure that it is a POST request.
/*if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0) {
    throw new Exception('Request method must be POST!');
}

//Make sure that the content type of the POST request has been set to application/json
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strcasecmp($contentType, 'application/json') != 0) {
    throw new Exception('Content type must be: application/json');
}*/

//Receive the RAW post data.
$content = trim(file_get_contents("php://input"));

//Attempt to decode the incoming RAW post data from JSON.
$decoded = json_decode($content, true);

//If json_decode failed, the JSON is invalid.
/*if (!is_array($decoded)) {
    throw new Exception('Received content contained invalid JSON!');
}*/


function covid19ImpactEstimator($data)
{

    if (!empty($data)) {
        // set response code - 200 OK
        http_response_code(200);

        $name = $data["region"]["name"];
        $avgAge = $data["region"]["avgAge"];
        $avgDailyIncomeInUSD = $data["region"]["avgDailyIncomeInUSD"];
        $avgDailyIncomePopulation = $data["region"]["avgDailyIncomePopulation"];
        $periodType = $data["periodType"];
        $timeToElapse = $data["timeToElapse"];
        $reportedCases = $data["reportedCases"];
        $population = $data["population"];
        $totalHospitalBeds = $data["totalHospitalBeds"];

        $currentlyInfected = impactCurrentlyInfected($reportedCases);
        $severeCurrentlyInfected = severeCurrentlyInfected($reportedCases);

        $impactInfectionsByRequestedTime = infectionsByRequestedTime($currentlyInfected, $periodType, $timeToElapse);
        $severeInfectionsByRequestedTime = infectionsByRequestedTime($severeCurrentlyInfected, $periodType, $timeToElapse);;

        $impactSevereCasesByRequestedTime = 0.15 * $impactInfectionsByRequestedTime;
        $severeCasesByRequestedTime = 0.15 * $severeInfectionsByRequestedTime;

        $impactHospitalBedsByRequestedTime = availableHospitalBeds($totalHospitalBeds, $impactSevereCasesByRequestedTime);
        $severeHospitalBedsByRequestedTime = availableHospitalBeds($totalHospitalBeds, $severeCasesByRequestedTime);

        $casesForICUByRequestedTime = casesForICUByRequestedTime($impactInfectionsByRequestedTime);
        $severeCasesForICUByRequestedTime = casesForICUByRequestedTime($severeInfectionsByRequestedTime);

        $casesForVentilatorsByRequestedTime = casesForVentilatorsByRequestedTime($impactInfectionsByRequestedTime);
        $severeCasesForVentilatorsByRequestedTime = casesForVentilatorsByRequestedTime($severeInfectionsByRequestedTime);

        $dollarsInFlight = dollarsInFlight($impactInfectionsByRequestedTime,$periodType, $timeToElapse,
            $avgDailyIncomeInUSD, $avgDailyIncomePopulation);
        $severeDollarsInFlight = dollarsInFlight($severeInfectionsByRequestedTime,$periodType, $timeToElapse,
            $avgDailyIncomeInUSD, $avgDailyIncomePopulation);

        $responseImpact = array(
            "currentlyInfected" => $currentlyInfected,
            "infectionsByRequestedTime" => (int)$impactInfectionsByRequestedTime,
            "severeCasesByRequestedTime" => (int)$impactSevereCasesByRequestedTime,
            "hospitalBedsByRequestedTime" => (int)$impactHospitalBedsByRequestedTime,
            "casesForICUByRequestedTime" => (int)$casesForICUByRequestedTime,
            "casesForVentilatorsByRequestedTime" => (int)$casesForVentilatorsByRequestedTime,
            "dollarsInFlight" => (int)$dollarsInFlight
        );

        $responseSevereImpact = array(
            "currentlyInfected" => $severeCurrentlyInfected,
            "infectionsByRequestedTime" => (int)$severeInfectionsByRequestedTime,
            "severeCasesByRequestedTime" => (int)$severeCasesByRequestedTime,
            "hospitalBedsByRequestedTime" => (int)$severeHospitalBedsByRequestedTime,
            "casesForICUByRequestedTime" => (int)$severeCasesForICUByRequestedTime,
            "casesForVentilatorsByRequestedTime" => (int)$severeCasesForVentilatorsByRequestedTime,
            "dollarsInFlight" => (int)$severeDollarsInFlight
        );


       return array(
           "data" => $data,
           "impact" => $responseImpact,
               "severeImpact" => $responseSevereImpact
       );



    }else{
        // set response code - 400 bad request
        http_response_code(400);

        return array("message" => "Data should not be empty");
    }

}

//print_r( covid19ImpactEstimator($decoded));



function periodConverter($periodType, $timeToElapse)
{
    $days = 0;
    switch (strtolower($periodType)) {
        case "months":
            $days = 30 * $timeToElapse;
            break;
        case "weeks":
            $days = 7 * $timeToElapse;
            break;
        case "days":
            $days = $timeToElapse;
            break;
        default:
            return "Please enter a valid period type or duration";
    }

    return $days;
}

function impactCurrentlyInfected($reportedCases)
{
    return $reportedCases * 10;
}

function severeCurrentlyInfected($reportedCases)
{
    return $reportedCases * 50;
}

function infectionsByRequestedTime($currentlyInfected, $periodType, $timeToElapse)
{
    $factor = floor(periodConverter($periodType, $timeToElapse) / 3);

    return $currentlyInfected * pow(2, $factor);

}

function availableHospitalBeds($totalHospitalBeds, $cases)
{
    //$availableBeds = floor(0.35 * $totalHospitalBeds);
    $availableBeds = 0.35 * $totalHospitalBeds;

    return $availableBeds - $cases;
}

function casesForICUByRequestedTime($infectionsByRequestedTime)
{
    return floor(0.05 * $infectionsByRequestedTime);
}

function casesForVentilatorsByRequestedTime($infectionsByRequestedTime)
{
    return floor(0.02 * $infectionsByRequestedTime);
}

function dollarsInFlight($infectionsByRequestedTime,$periodType, $timeToElapse,
                         $avgDailyIncomeInUSD, $avgDailyIncomePopulation)
{
    $days = floor(periodConverter($periodType, $timeToElapse));

    $dollars =  ($infectionsByRequestedTime * $avgDailyIncomeInUSD * $avgDailyIncomePopulation) / $days;
    return floor($dollars);
}