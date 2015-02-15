<?php
namespace Rocketeer\Services;

use Mockery;
use Mockery\MockInterface;
use Rocketeer\TestCases\RocketeerTestCase;

class CredentialsGathererTest extends RocketeerTestCase
{
    /**
     * @type string
     */
    protected $key = '/.ssh/id_rsa';

    protected $repository;
    protected $username;
    protected $password;
    protected $host;

    public function setUp()
    {
        parent::setUp();

        $this->repository = 'git@github.com:Anahkiasen/rocketeer.git';
        $this->username   = 'anahkiasen';
        $this->password   = 'foobar';
        $this->host       = 'some.host';
    }

    public function testIgnoresPlaceholdersWhenFillingCredentials()
    {
        $this->mockAnswers(array(
            'No repository is set for [repository]' => $this->repository,
            'No username is set for [repository]'   => $this->username,
            'No password is set for [repository]'   => $this->password,
        ));
        $this->command->shouldReceive('option')->andReturn(null);

        $this->givenConfiguredRepositoryCredentials(['repository' => '{foobar}']);

        $this->assertStoredCredentialsEquals(array(
            'repository' => $this->repository,
            'username'   => $this->username,
            'password'   => $this->password,
        ));

        $this->credentialsGatherer->getRepositoryCredentials();
    }

    public function testCanGetRepositoryCredentials()
    {
        $this->mockAnswers(array(
            'No repository is set for [repository]' => $this->repository,
            'No username is set for [repository]'   => $this->username,
            'No password is set for [repository]'   => $this->password,
        ));
        $this->command->shouldReceive('option')->andReturn(null);
        $this->command->shouldReceive('askWith')->with(Mockery::any(), 'key', Mockery::any())->never();

        $this->givenConfiguredRepositoryCredentials([]);

        $this->assertStoredCredentialsEquals(array(
            'repository' => $this->repository,
            'username'   => $this->username,
            'password'   => $this->password,
        ));

        $this->credentialsGatherer->getRepositoryCredentials();
    }

    public function testDoesntAskForRepositoryCredentialsIfUneeded()
    {
        $this->mockAnswers();
        $this->command->shouldReceive('option')->andReturn(null);

        $this->givenConfiguredRepositoryCredentials([
            'repository' => $this->repository,
            'username'   => null,
            'password'   => null,
        ], false);
        $this->assertStoredCredentialsEquals(array(
            'repository' => $this->repository,
            'username'   => null,
            'password'   => null,
        ));

        $this->credentialsGatherer->getRepositoryCredentials();
    }

    public function testCanFillRepositoryCredentialsIfNeeded()
    {
        $this->mockAnswers(array(
            'No username is set for [repository]' => $this->username,
            'No password is set for [repository]' => null,
        ));
        $this->command->shouldReceive('option')->andReturn(null);

        $this->givenConfiguredRepositoryCredentials(['repository' => $this->repository], true);

        $this->assertStoredCredentialsEquals(array(
            'repository' => $this->repository,
            'username'   => 'anahkiasen',
            'password'   => null,
        ));

        $this->credentialsGatherer->getRepositoryCredentials();
    }

    public function testCanGetServerCredentialsIfNoneDefined()
    {
        $this->swapConfig(array(
            'rocketeer::connections' => [],
        ));

        $this->mockAnswers(array(
            'No host is set for [production]'     => $this->host,
            'No username is set for [production]' => $this->username,
            'No password is set for [production]' => $this->password,
        ));

        $this->command->shouldReceive('askWith')->with('No connections have been set, please create one:', 'production')->andReturn('production');
        $this->command->shouldReceive('askWith')->with(
            'No password or SSH key is set for [production], which would you use?',
            'key', ['key', 'password']
        )->andReturn('password');
        $this->command->shouldReceive('option')->andReturn(null);

        $this->credentialsGatherer->getServerCredentials();

        $credentials = $this->credentials->getServerCredentials('production', 0);
        $this->assertEquals(array(
            'host'      => $this->host,
            'username'  => $this->username,
            'password'  => $this->password,
            'keyphrase' => null,
            'key'       => null,
            'agent'     => null,
        ), $credentials);
    }

    public function testCanPassCredentialsAsFlags()
    {
        $this->swapConfig(array(
            'rocketeer::connections' => [],
        ));

        $this->mockAnswers(array(
            'No username is set for [production]' => $this->username,
        ));

        $this->command->shouldReceive('askWith')->with('No connections have been set, please create one:', 'production')->andReturn('production');
        $this->command->shouldReceive('askWith')->with(
            'No password or SSH key is set for [production], which would you use?',
            'key', ['key', 'password']
        )->andReturn('password');
        $this->command->shouldReceive('option')->withNoArgs()->andReturn([
            'host'     => $this->host,
            'password' => $this->password,
        ]);

        $this->credentialsGatherer->getServerCredentials();

        $credentials = $this->credentials->getServerCredentials('production', 0);
        $this->assertEquals(array(
            'host'      => $this->host,
            'username'  => $this->username,
            'password'  => $this->password,
            'keyphrase' => null,
            'key'       => null,
            'agent'     => null,
        ), $credentials);
    }

    public function testCanGetCredentialsForSpecifiedConnection()
    {
        $key = $this->paths->getDefaultKeyPath();
        $this->mockAnswers(array(
            'No host is set for [staging]'           => $this->host,
            'No username is set for [staging]'       => $this->username,
            'If a keyphrase is required, provide it' => 'KEYPHRASE',
        ));

        $this->command->shouldReceive('option')->with('on')->andReturn('staging');
        $this->command->shouldReceive('option')->andReturn(null);
        $this->command->shouldReceive('askWith')->with(
            'Please enter the full path to your key', $key
        )->andReturn($key);
        $this->command->shouldReceive('askWith')->with(
            'No password or SSH key is set for [staging], which would you use?',
            'key', ['key', 'password']
        )->andReturn('key');

        $this->credentialsGatherer->getServerCredentials();

        $credentials = $this->credentials->getServerCredentials('staging', 0);
        $this->assertEquals(array(
            'host'      => $this->host,
            'username'  => $this->username,
            'password'  => null,
            'keyphrase' => 'KEYPHRASE',
            'key'       => $key,
            'agent'     => null,
        ), $credentials);
    }

    public function testCanHaveMultipleTypesOfCredentials()
    {
        $this->swapConnections(array(
            'production' => [
                'host'      => $this->host,
                'username'  => '',
                'password'  => false,
                'key'       => '',
                'keyphrase' => true,
            ],
        ));

        $this->mockAnswers(array(
            'No username is set for [production]'    => $this->username,
            'If a keyphrase is required, provide it' => 'keyphrase',
        ));
        $this->command->shouldReceive('askWith')->with('Please enter the full path to your key', Mockery::any())->once()->andReturn($this->key);
        $this->command->shouldReceive('askWith')->with('No password or SSH key is set for [production], which would you use?', Mockery::any(), Mockery::any())->once()->andReturn('key');

        $this->credentialsGatherer->getServerCredentials();

        $credentials = $this->credentials->getServerCredentials('production', 0);
        $this->assertEquals(array(
            'host'      => $this->host,
            'username'  => $this->username,
            'password'  => null,
            'keyphrase' => 'keyphrase',
            'key'       => $this->key,
            'agent'     => null,
        ), $credentials);

        $stored = $this->localStorage->get('connections.production.servers.0');
        $this->assertEquals(array(
            'host'     => $this->host,
            'username' => $this->username,
            'password' => null,
            'key'      => $this->key,
            'agent'    => null,
        ), $stored);
    }

    public function testDoesntAskForKeyphraseOnlyOnce()
    {
        $this->swapConnections(array(
            'production' => [
                'host'      => $this->host,
                'username'  => '',
                'password'  => false,
                'key'       => $this->key,
                'keyphrase' => true,
            ],
        ));

        $this->mockAnswers(array(
            'No username is set for [production]'    => $this->username,
            'If a keyphrase is required, provide it' => 'keyphrase',
        ));

        $this->credentialsGatherer->getServerCredentials();
    }

    public function testPreservesCredentialsTypes()
    {
        $this->localStorage->set('connections.production.servers.0', array(
            'host'     => $this->host,
            'username' => '',
            'password' => false,
            'agent'    => true,
        ));

        $this->credentialsGatherer->getServerCredentials();
        $credentials = $this->localStorage->get('connections.production.servers.0');

        $this->assertEquals($this->host, $credentials['host']);
        $this->assertEquals('', $credentials['username']);
        $this->assertFalse($credentials['password']);
        $this->assertArrayNotHasKey('agent', $credentials);
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// HELPERS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Mock a set of question/answers
     *
     * @param array $answers
     */
    protected function mockAnswers($answers = array())
    {
        $this->mock('rocketeer.command', 'Command', function (MockInterface $mock) use ($answers) {
            if (!$answers) {
                return $mock->shouldReceive('ask')->never();
            }

            foreach ($answers as $question => $answer) {
                $question = strpos($question, 'is set for') !== false ? $question.', please provide one:' : $question;
                $method   = strpos($question, 'password') !== false || strpos($question, 'keyphrase') !== false ? 'askSecretly' : 'askWith';
                $mock     = $mock->shouldReceive($method)->with($question)->andReturn($answer);
            }

            return $mock;
        });
    }

    /**
     * Assert a certain set of credentials are saved to storage
     *
     * @param array $credentials
     */
    protected function assertStoredCredentialsEquals(array $credentials)
    {
        $this->mock('rocketeer.storage.local', 'LocalStorage', function (MockInterface $mock) use ($credentials) {
            return $mock->shouldReceive('set')->with('credentials', $credentials);
        });
    }

    /**
     * @param array   $credentials
     * @param boolean $need
     */
    protected function givenConfiguredRepositoryCredentials(array $credentials, $need = false)
    {
        $this->mock('rocketeer.credentials.handler', 'CredentialsHandler', function (MockInterface $mock) use (
            $need,
            $credentials
        ) {
            return $mock
                ->shouldReceive('repositoryNeedsCredentials')->andReturn($need)
                ->shouldReceive('getRepositoryCredentials')->andReturn($credentials);
        });
    }
}
