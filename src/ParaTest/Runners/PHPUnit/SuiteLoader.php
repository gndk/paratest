<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\Parser;

class SuiteLoader
{
    protected $files = array();
    protected $loadedSuites = array();

    private static $testPattern = '/.+Test\.php$/';
    private static $filePattern = '/.+\.php$/';
    private static $dotPattern = '/([.]+)$/';
    private static $testMethod = '/^test/';

    public function __construct($options = null)
    {
        if($options && !$options instanceof Options)
            throw new \InvalidArgumentException("SuiteLoader options must be null or of type Options");
        $this->options = $options;
    }

    public function getSuites()
    {
        return $this->loadedSuites;
    }

    public function getTestMethods()
    {
        $methods = array();
        foreach($this->loadedSuites as $suite)
            $methods = array_merge($methods, $suite->getFunctions());

        return $methods;
    }

    public function load($path = '')
    {
        $configuration = @$this->options->filtered['configuration'] ?: new Configuration('');
        if($path) $this->loadPath($path);
        else if($suites = $configuration->getSuites())
            foreach($suites as $name => $path)
                $this->loadPath($path);
        if(!$this->files) throw new \RuntimeException("No path or configuration provided (tests must end with Test.php)");
        $this->initSuites();
    }

    private function loadPath($path)
    {
        $path = $path ? : $this->options->path;
        if (!file_exists($path))
            throw new \InvalidArgumentException("$path is not a valid directory or file");
        if (is_dir($path))
            $this->loadDir($path);
        else if (file_exists($path))
            $this->loadFile($path);
    }

    private function loadDir($path)
    {
        $files = scandir($path);
        foreach($files as $file)
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file);
    }

    private function loadFile($path)
    {
        $this->tryLoadTests($path, true);
    }

    private function tryLoadTests($path, $relaxTestPattern = false)
    {
        $pattern = ($relaxTestPattern) ? 'filePattern' : 'testPattern';
        if(preg_match(self::$$pattern, $path))
            $this->files[] = $path;

        if(!preg_match(self::$dotPattern, $path) && is_dir($path))
            $this->loadDir($path);
    }

    private function initSuites()
    {
        foreach ($this->files as $path) {
            $parser = new Parser($path);
            if ($class = $parser->getClass()) {
                // TODO: ExecutableTest must have the class name too
                $this->loadedSuites[$path] = new Suite(
                    $path, 
                    array_map(
                        function($fn) use ($path) {
                            return new TestMethod($path, $fn->getName());
                        }, 
                        $class->getMethods($this->options ? $this->options->annotations : array())
                    ),
                    // TODO: does this comprehend the namespace?
                    $class->getName()
                );
            }
        }
    }
}
