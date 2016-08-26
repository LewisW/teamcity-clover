<?php

/**
 * Script, which publishes the code coverage metrics of the clover.xml from PHPUnit to TeamCity.
 *
 * @author Michel Hunziker <info@michelhunziker.com>
 * @copyright Copyright (c) 2016 Michel Hunziker <info@michelhunziker.com>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD-3-Clause License
 */


if ($argc < 2) {
    echo "Path to the clover.xml is required.\n";
    exit(1);
}

$options = getopt('', array('crap-threshold:'));
$crapThreshold = array_key_exists('crap-threshold', $options) ? (float) $options['crap-threshold'] : 30;
$data = array(
    'CodeCoverageAbsLTotal' => 0,
    'CodeCoverageAbsLCovered' => 0,
    'CodeCoverageAbsBTotal' => 0,
    'CodeCoverageAbsBCovered' => 0,
    'CodeCoverageAbsMTotal' => 0,
    'CodeCoverageAbsMCovered' => 0,
    'CodeCoverageAbsCTotal' => 0,
    'CodeCoverageAbsCCovered' => 0,
    'Files' => 0,
    'LinesOfCode' => 0,
    'NonCommentLinesOfCode' => 0
);

foreach (array_slice($argv, 1) as $path) {
    if (!file_exists($path)) {
        echo "clover.xml does not exist: $path\n";
        exit(1);
    }


    echo "Parsing clover.xml from: $path\n";
    $cloverXml = new SimpleXMLElement($path, null, true);
    $metrics = $cloverXml->project->metrics;

    if (!$metrics) {
        echo "clover.xml does not contain code coverage metrics.\n";
        exit(1);
    }

    $coveredClasses = 0;
    foreach ($cloverXml->xpath('//class') as $class) {
        if ((int) $class->metrics['coveredmethods'] === (int) $class->metrics['methods']) {
            $coveredClasses++;
        }
    }

    $data['CodeCoverageAbsLTotal'] += (int) $metrics['elements'];
    $data['CodeCoverageAbsLCovered'] += (int) $metrics['coveredelements'];
    $data['CodeCoverageAbsBTotal'] += (int) $metrics['statements'];
    $data['CodeCoverageAbsBCovered'] += (int) $metrics['coveredstatements'];
    $data['CodeCoverageAbsMTotal'] += (int) $metrics['methods'];
    $data['CodeCoverageAbsMCovered'] += (int) $metrics['coveredmethods'];
    $data['CodeCoverageAbsCTotal'] += (int) $metrics['classes'];
    $data['CodeCoverageAbsCCovered'] += $coveredClasses;
    $data['Files'] += (int) $metrics['files'];
    $data['LinesOfCode'] += (int) $metrics['loc'];
    $data['NonCommentLinesOfCode'] += (int) $metrics['ncloc'];


    if ($crapThreshold) {
        $crapValues = array();
        $crapAmount = 0;
        foreach ($cloverXml->xpath('//@crap') as $crap) {
            $crap = (float) $crap;
            $crapValues[] = $crap;
            if ($crap >= $crapThreshold) {
                $crapAmount++;
            }
        }

        $crapValuesCount = count($crapValues);
        $crapTotal = array_sum($crapValues);

        $data['CRAPAmount'] = $crapAmount;
        $data['CRAPPercent'] = $crapValuesCount ? $crapAmount / $crapValuesCount * 100 : 0;
        $data['CRAPTotal'] = $crapTotal;
        $data['CRAPAverage'] = $crapValuesCount ? $crapTotal / $crapValuesCount : 0;
        $data['CRAPMaximum'] = max($crapValues);
    }
}

foreach ($data as $key => $value) {
    if (is_float($value)) {
        $value = round($value, 6);
    }

    echo "##teamcity[buildStatisticValue key='$key' value='$value']\n";
}

echo "TeamCity has been notified of code coverage metrics.\n";
