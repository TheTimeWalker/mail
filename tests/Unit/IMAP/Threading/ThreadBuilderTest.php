<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace mail\lib\IMAP\Threading;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\IMAP\Threading\Container;
use OCA\Mail\IMAP\Threading\Message;
use OCA\Mail\IMAP\Threading\ThreadBuilder;
use function array_values;

class ThreadBuilderTest extends TestCase {

	/** @var ThreadBuilder */
	private $builder;

	protected function setUp(): void {
		parent::setUp();

		$this->builder = new ThreadBuilder();
	}

	/**
	 * @param Container[] $set
	 *
	 * @return array
	 */
	private function abstract(array $set): array {
		return array_map(function (Container $container) {
			return [
				'id' => (($message = $container->getMessage()) !== null ? $message->getId() : null),
				'children' => $this->abstract($container->getChildren()),
			];
		}, array_values($set));
	}

	public function testBuildEmpty(): void {
		$messages = [];

		$result = $this->builder->build($messages);

		$this->assertEquals([], $result);
	}

	public function testBuildFlat(): void {
		$messages = [
			new Message('s1', 'id1', []),
			new Message('s2', 'id2', []),
			new Message('s3', 'id3', []),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [],
				],
				[
					'id' => 'id2',
					'children' => [],
				],
				[
					'id' => 'id3',
					'children' => [],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildOneDeep(): void {
		$messages = [
			new Message('s1', 'id1', []),
			new Message('Re:s1', 'id2', ['id1']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildOneDeepMismatchingSubjects(): void {
		$messages = [
			new Message('s1', 'id1', []),
			new Message('s2', 'id2', ['id1']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildOneDeepNoReferences(): void {
		$messages = [
			new Message('s1', 'id1', []),
			new Message('Re:s1', 'id2', []),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildTwoDeep(): void {
		// 1
		// |
		// 2
		// |
		// 3
		$messages = [
			new Message('s1', 'id1', []),
			new Message('s2', 'id2', ['id1']),
			new Message('s3', 'id3', ['id2']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [
								[
									'id' => 'id3',
									'children' => [],
								],
							],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildFourDeep(): void {
		// 1
		// |
		// 2
		// |
		// 3
		// |
		// 4
		$messages = [
			new Message('s1', 'id1', []),
			new Message('Re:s1', 'id2', ['id1']),
			new Message('Re:s1', 'id3', ['id2']),
			new Message('Re:s1', 'id4', ['id3']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [
								[
									'id' => 'id3',
									'children' => [
										[
											'id' => 'id4',
											'children' => [],
										],
									],
								],
							],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildTree(): void {
		//        1
		//      /   \
		//     2     3
		//    / \   / \
		//   4   5 6   7
		$messages = [
			new Message('s1', 'id1', []),
			new Message('Re:s1', 'id2', ['id1']),
			new Message('Re:s1', 'id3', ['id1']),
			new Message('Re:s1', 'id4', ['id1', 'id2']),
			new Message('Re:s1', 'id5', ['id1', 'id2']),
			new Message('Re:s1', 'id6', ['id1', 'id3']),
			new Message('Re:s1', 'id7', ['id1', 'id3']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [
								[
									'id' => 'id4',
									'children' => [],
								],
								[
									'id' => 'id5',
									'children' => [],
								],
							],
						],
						[
							'id' => 'id3',
							'children' => [
								[
									'id' => 'id6',
									'children' => [],
								],
								[
									'id' => 'id7',
									'children' => [],
								],
							],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildTreePartialRefs(): void {
		//        1
		//      /   \
		//     2     3
		//    / \   / \
		//   4   5 6   7
		$messages = [
			new Message('s1', 'id1', []),
			new Message('Re:s1', 'id2', ['id1']),
			new Message('Re:s1', 'id3', ['id1']),
			new Message('Re:s1', 'id4', ['id2']),
			new Message('Re:s1', 'id5', ['id2']),
			new Message('Re:s1', 'id6', ['id3']),
			new Message('Re:s1', 'id7', ['id3']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [
								[
									'id' => 'id4',
									'children' => [],
								],
								[
									'id' => 'id5',
									'children' => [],
								],
							],
						],
						[
							'id' => 'id3',
							'children' => [
								[
									'id' => 'id6',
									'children' => [],
								],
								[
									'id' => 'id7',
									'children' => [],
								],
							],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildCyclic(): void {
		$messages = [
			new Message('s1', 'id1', ['id2']),
			new Message('s2', 'id2', ['id1']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id2',
					'children' => [
						[
							'id' => 'id1',
							'children' => [],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildSiblingsWithRoot(): void {
		$messages = [
			new Message('s1', 'id1', []),
			new Message('s2', 'id2', ['id1']),
			new Message('s3', 'id3', ['id1']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => 'id1',
					'children' => [
						[
							'id' => 'id2',
							'children' => [],
						],
						[
							'id' => 'id3',
							'children' => [],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}

	public function testBuildSiblingsWithoutRoot(): void {
		$messages = [
			new Message('Re:s1', 'id2', ['id1']),
			new Message('Re:s2', 'id3', ['id1']),
		];

		$result = $this->builder->build($messages);

		$this->assertEquals(
			[
				[
					'id' => null,
					'children' => [
						[
							'id' => 'id2',
							'children' => [],
						],
						[
							'id' => 'id3',
							'children' => [],
						],
					],
				],
			],
			$this->abstract($result)
		);
	}
}
