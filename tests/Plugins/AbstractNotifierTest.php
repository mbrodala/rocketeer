<?php
namespace Rocketeer\Plugins;

use Rocketeer\Dummies\DummyNotifier;
use Rocketeer\TestCases\RocketeerTestCase;

class AbstractNotifierTest extends RocketeerTestCase
{
	public function setUp()
	{
		parent::setUp();

		$this->swapConfig(array(
			'rocketeer::stages.stages' => array('staging', 'production'),
			'rocketeer::hooks'         => array(),
			'rocketeer::connections'   => array(
				'production' => array(
					'host' => 'foo.bar.com'
				),
			),
		));
		$this->tasks->registerConfiguredEvents();

		$this->notifier = new DummyNotifier($this->app);
		$this->tasks->plugin($this->notifier);
	}

	public function testCanAskForNameIfNoneProvided()
	{
		$this->expectOutputString('foobar finished deploying branch "master" on "staging@production" (foo.bar.com)');

		$this->mockCommand([], ['ask' => 'foobar']);
		$this->mock('rocketeer.storage.local', 'LocalStorage', function ($mock) {
			return $mock
				->shouldReceive('get')->with('connections')
				->shouldReceive('get')->with('notifier.name')->andReturn(null)
				->shouldReceive('set')->once()->with('notifier.name', 'foobar');
		});
		$this->mock('rocketeer.connections', 'ConnectionsHandler', function ($mock) {
			return $mock
				->shouldReceive('getRepositoryBranch')->andReturn('master')
				->shouldReceive('getStage')->andReturn('staging')
				->shouldReceive('getConnection')->andReturn('production')
				->shouldReceive('getServer')->andReturn('0')
				->shouldReceive('getServerCredentials')->andReturn(['host' => 'foo.bar.com']);
		});

		$this->task('Deploy')->fireEvent('before');
	}

	public function testCanAppendStageToDetails()
	{
		$this->expectOutputString('Jean Eude finished deploying branch "master" on "staging@production" (foo.bar.com)');
		$this->localStorage->set('notifier.name', 'Jean Eude');
		$this->connections->setStage('staging');
		$this->notifier = new DummyNotifier($this->app);

		$this->task('Deploy')->fireEvent('before');
	}

	public function testCanSendDeploymentsNotifications()
	{
		$this->expectOutputString('Jean Eude finished deploying branch "master" on "production" (foo.bar.com)');
		$this->localStorage->set('notifier.name', 'Jean Eude');

		$this->task('Deploy')->fireEvent('after');
	}

	public function testDoesntSendNotificationsInPretendMode()
	{
		$this->expectOutputString('');
		$this->localStorage->set('notifier.name', 'Jean Eude');

		$this->pretendTask('Deploy')->fireEvent('after');
	}
}