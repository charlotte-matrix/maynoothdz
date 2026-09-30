<?php

namespace MapSVG;

require_once __DIR__ . '/Json.php';
require_once __DIR__ . '/JsToJson.php';
class JsonTest
{
  private static function normalizeJson($json)
  {
    // Decode and encode to normalize formatting
    $decoded = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      // Encode without pretty print to remove whitespace differences
      return json_encode($decoded);
    }
    return false;
  }

  private static function runTest($input, $expected, $testName)
  {
    $result = \MapSVG\JsToJson::convertToJson($input);

    // Normalize both expected and result
    $normalizedExpected = self::normalizeJson($expected);
    $normalizedResult = self::normalizeJson($result);


    $passed = ($normalizedResult === $normalizedExpected);

    echo "\nTest: " . $testName . "\n";
    // echo "Input:    " . $input . "\n";
    // echo "Expected: " . $expected . "\n";
    echo "Got:      " . $result . "\n";
    echo "Status:   " . ($passed ? "✅ PASSED" : "❌ FAILED") . "\n";
    if (!$passed) {
      echo "Debug:    " . \MapSVG\Json::debug() . "\n";
    }

    return $passed;
  }



  public static function runTests()
  {


    $tests = [
      [
        'name' => 'Unquoted property at start',
        'input' => '{source:"1","markerLastID":3}',
        'expected' => '{"source":"1","markerLastID":3}'
      ],
      [
        'name' => 'Multiple properties',
        'input' => '{source:"/path/file.svg",width:100}',
        'expected' => '{"source":"/path/file.svg","width":100}'
      ],
      [
        'name' => 'Single quotes',
        'input' => '{source:\'/path/file.svg\'}',
        'expected' => '{"source":"/path/file.svg"}'
      ]
    ];

    $passed = 0;
    $total = count($tests);

    foreach ($tests as $test) {
      if (self::runTest($test['input'], $test['expected'], $test['name'])) {
        $passed++;
      }
    }

    echo "\nSummary: $passed/$total tests passed\n";
  }
}

// Run the tests
JsonTest::runTests();
