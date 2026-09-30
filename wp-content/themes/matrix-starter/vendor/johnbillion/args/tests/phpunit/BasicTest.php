<?php

declare(strict_types=1);

namespace Args\Tests;

use PHPUnit\Framework\TestCase;

final class BasicTest extends TestCase {

	use \FalseyAssertEqualsDetector\Test;

	public function testBasicParamsAreCorrectlyConvertedToArray(): void {
		$args = new \Args\WP_Query;

		$args->attachment_id = 123;
		$args->author = '1,2,3';

		$expected = [
			'attachment_id' => 123,
			'author' => '1,2,3',
		];
		$actual = $args->toArray();

		self::assertSame( $expected, $actual );
	}

	public function testUnknownParamsAreIncludedInArray(): void {
		$args = new \Args\WP_Query;

		$args->attachment_id = 123;
		$args->name = 'world';

		$expected = [
			'attachment_id' => 123,
			'name' => 'world',
		];
		$actual = $args->toArray();

		self::assertSame( $expected, $actual );
	}
}
