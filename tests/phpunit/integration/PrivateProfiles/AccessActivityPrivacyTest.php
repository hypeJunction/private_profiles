<?php

namespace PrivateProfiles;

use Elgg\Database\Select;
use Elgg\Event;
use Elgg\IntegrationTestCase;
use Elgg\PrivateProfiles\Access;

/**
 * Tests for Access::applyActivityPrivacy() — the get_sql,access event handler.
 *
 * Regression guard for the 5.x → 6.x refactor that replaced raw string
 * interpolation of the dbprefix into a parameterized NOT EXISTS subquery built
 * via the QueryBuilder.
 */
class AccessActivityPrivacyTest extends IntegrationTestCase {

	public function up() {
	}

	public function down() {
	}

	public function getPluginID(): string {
		return 'private_profiles';
	}

	/**
	 * Build an \Elgg\Event mock for "get_sql","access" with the given params + value.
	 */
	protected function makeEvent(array $params, array $value): Event {
		$event = $this->getMockBuilder(Event::class)->disableOriginalConstructor()->getMock();
		$event->method('getName')->willReturn('get_sql');
		$event->method('getType')->willReturn('access');
		$event->method('getValue')->willReturn($value);
		$event->method('getParam')->willReturnCallback(function ($key, $default = null) use ($params) {
			return $params[$key] ?? $default;
		});
		$event->method('getParams')->willReturn($params);
		return $event;
	}

	public function testReturnsVoidWhenInActionContext(): void {
		elgg_push_context('action');
		try {
			$qb = Select::fromTable('entities', 'e');
			$event = $this->makeEvent(['query_builder' => $qb, 'table_alias' => 'e'], ['ands' => [], 'ors' => []]);
			$this->assertNull(Access::applyActivityPrivacy($event));
		} finally {
			elgg_pop_context();
		}
	}

	public function testReturnsVoidWhenLoggedInViewer(): void {
		$qb = Select::fromTable('entities', 'e');
		$event = $this->makeEvent(
			['query_builder' => $qb, 'table_alias' => 'e', 'user_guid' => 42],
			['ands' => [], 'ors' => []]
		);
		$this->assertNull(Access::applyActivityPrivacy($event));
	}

	public function testReturnsVoidWhenIgnoreAccess(): void {
		$qb = Select::fromTable('entities', 'e');
		$event = $this->makeEvent(
			['query_builder' => $qb, 'table_alias' => 'e', 'ignore_access' => true],
			['ands' => [], 'ors' => []]
		);
		$this->assertNull(Access::applyActivityPrivacy($event));
	}

	public function testReturnsVoidWhenNoQueryBuilderInPayload(): void {
		// Defensive: pre-6.x event shape (no query_builder param)
		$event = $this->makeEvent(['table_alias' => 'e'], ['ands' => [], 'ors' => []]);
		$this->assertNull(Access::applyActivityPrivacy($event));
	}

	public function testAddsParameterizedNotExistsClause(): void {
		$qb = Select::fromTable('entities', 'e');

		$event = $this->makeEvent(
			['query_builder' => $qb, 'table_alias' => 'e'],
			['ands' => [], 'ors' => []]
		);

		$result = Access::applyActivityPrivacy($event);

		$this->assertIsArray($result);
		$this->assertNotEmpty($result['ands']);

		$clause = end($result['ands']);

		// Shape: NOT EXISTS subquery against metadata table
		$this->assertStringStartsWith('NOT EXISTS (', $clause);
		$this->assertStringContainsString('metadata', $clause);
		$this->assertStringContainsString('pp_md.entity_guid = e.guid', $clause);
		$this->assertStringContainsString('pp_md.entity_guid = e.owner_guid', $clause);

		// Critical: no raw string interpolation of the setting name/value —
		// they must be bound as named parameters on the outer QueryBuilder.
		$this->assertStringNotContainsString("plugin:user_setting:private_profiles:user_activity_setting", $clause);
		$this->assertStringNotContainsString("'members'", $clause);
		$this->assertMatchesRegularExpression('/pp_md\.name = :qb\d+/', $clause);
		$this->assertMatchesRegularExpression('/pp_md\.value = :qb\d+/', $clause);

		// The outer QueryBuilder should have bound exactly these two values
		// (regardless of any extra params already in the bag).
		$params = $qb->getParameters();
		$values = array_values($params);
		$this->assertContains('plugin:user_setting:private_profiles:user_activity_setting', $values);
		$this->assertContains('members', $values);
	}

	public function testWorksWithoutTableAlias(): void {
		$qb = Select::fromTable('entities');

		$event = $this->makeEvent(
			['query_builder' => $qb],
			['ands' => [], 'ors' => []]
		);

		$result = Access::applyActivityPrivacy($event);
		$clause = end($result['ands']);

		$this->assertStringContainsString('pp_md.entity_guid = guid', $clause);
		$this->assertStringContainsString('pp_md.entity_guid = owner_guid', $clause);
	}
}
