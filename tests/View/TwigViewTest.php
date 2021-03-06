<?php

/**
 * This file is part of TwigView.
 *
 ** (c) 2014 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use WyriHaximus\TwigView\Event\ConstructEvent;
use WyriHaximus\TwigView\Event\EnvironmentConfigEvent;
use WyriHaximus\TwigView\Lib\Loader;
use WyriHaximus\TwigView\View\TwigView;

/**
 * Class TwigViewTest
 */
class TwigViewTest extends TestCase
{

	/**
	 * @param $name
	 * @return ReflectionMethod
	 */
	protected static function getMethod($name)
	{
		$class = new ReflectionClass('WyriHaximus\TwigView\View\TwigView');
		$method = $class->getMethod($name);
		$method->setAccessible(true);
		return $method;
	}

	/**
	 * @param $name
	 * @return ReflectionProperty
	 */
	protected static function getProperty($name)
	{
		$class = new ReflectionClass('WyriHaximus\TwigView\View\TwigView');
		$property = $class->getProperty($name);
		$property->setAccessible(true);
		return $property;
	}

	protected function _hibernateListeners($eventKey)
	{
		$this->__preservedEventListeners[$eventKey] = EventManager::instance()->listeners($eventKey);

		foreach ($this->__preservedEventListeners[$eventKey] as $eventListener) {
			EventManager::instance()->detach($eventListener['callable'], $eventKey);
		}
	}

	protected function _wakeupListeners($eventKey)
	{
		if (isset($this->__preservedEventListeners[$eventKey])) {
			return;
		}

		foreach ($this->__preservedEventListeners[$eventKey] as $eventListener) {
			EventManager::instance()->attach(
				$eventListener['callable'],
				$eventKey,
				array(
					'passParams' => $eventListener['passParams'],
				)
			);
		}

		$this->__preservedEventListeners = array();
	}

	public function testInheritance()
	{
		$this->assertInstanceOf('Cake\View\View', new TwigView);
	}

	public function testConstruct()
	{
		$this->_hibernateListeners(ConstructEvent::EVENT);

		$callbackFired = false;
		$that = $this;
		$eventCallback = function ($event) use ($that, &$callbackFired) {
			$that->assertInstanceof('Twig_Environment', $event->subject()->getTwig());
			$callbackFired = true;
		};
		EventManager::instance()->attach($eventCallback, ConstructEvent::EVENT);

		new TwigView();

		EventManager::instance()->detach($eventCallback, ConstructEvent::EVENT);
		$this->_wakeupListeners(ConstructEvent::EVENT);

		$this->assertTrue($callbackFired);
	}

	public function testConstructConfig()
	{
        Configure::write(TwigView::ENV_CONFIG, [
            'true' => true,
        ]);

		$this->_hibernateListeners(EnvironmentConfigEvent::EVENT);

		$callbackFired = false;
		$that = $this;
		$eventCallback = function ($event) use ($that, &$callbackFired) {
			$that->assertInternalType('array', $event->getConfig());
			$that->assertTrue($event->getConfig()['true']);

			$callbackFired = true;
		};
		EventManager::instance()->attach($eventCallback, EnvironmentConfigEvent::EVENT);

		new TwigView();

		EventManager::instance()->detach($eventCallback, EnvironmentConfigEvent::EVENT);
		$this->_wakeupListeners(EnvironmentConfigEvent::EVENT);

		$this->assertTrue($callbackFired);
	}

	public function testGenerateHelperList()
	{
		$helpersArray = [
			'TestHelper',
		];

		$registery = Phake::mock('Cake\View\HelperRegistry');
		Phake::when($registery)->normalizeArray($helpersArray)->thenReturn(
			[
				[
					'class' => 'TestHelper',
				],
			]
		);

        $view = new TwigView(Phake::mock('Cake\Network\Request'), Phake::mock('Cake\Network\Response'), Phake::mock('Cake\Event\EventManager'));
        $view->TestHelper = 'foo:bar';
        $view->helpers = $helpersArray;

		self::getMethod('generateHelperList')->invoke($view);
		$this->assertSame(
			[
				'TestHelper' => 'foo:bar',
			],
			self::getProperty('helperList')->getValue($view)
		);
	}

	public function test_renderCtp()
	{
		$output = 'foo:bar with a beer';

		$twig = Phake::mock('Twig_Environment');

		$view = Phake::partialMock('WyriHaximus\TwigView\View\TwigView');
		Phake::when($view)->getTwig()->thenReturn($twig);

		$this->assertSame(
			$output,
			self::getMethod('_render')->invokeArgs(
				$view,
				[
					PLUGIN_REPO_ROOT . 'tests' . DS . 'test_app' . DS . 'Template' . DS . 'cakephp.ctp',
				]
			)
		);
	}

	public function test_renderTpl()
	{
		$output = 'foo:bar with a beer';

		$template = Phake::mock('Twig_TemplateInterface');

		$twig = Phake::mock('Twig_Environment');
		Phake::when($twig)->loadTemplate('foo.tpl')->thenReturn($template);

		$view = Phake::partialMock('WyriHaximus\TwigView\View\TwigView');
		Phake::when($view)->getTwig()->thenReturn($twig);
		Phake::when($template)->render(
			[
				'_view' => $view,
			]
		)->thenReturn($output);

		$this->assertSame(
			$output,
			self::getMethod('_render')->invokeArgs(
				$view,
				[
					'foo.tpl',
				]
			)
		);
	}

}
