<?php

namespace splitbrain\phpcli;

/**
 * Class CLI
 *
 * Your commandline script should inherit from this class and implement the abstract methods.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @license MIT
 */
abstract class CLI
{
    /** @var string the executed script itself */
    protected $bin;
    /** @var  Options the option parser */
    protected $options;
    /** @var  Colors */
    public $colors;

    /** @var array PSR-3 compatible loglevels and their prefix, color, output channel */
    protected $loglevel = array(
        'debug' => array('', Colors::C_LIGHTGRAY, STDOUT),
        'info' => array('ℹ ', Colors::C_CYAN, STDOUT),
        'notice' => array('☛ ', Colors::C_CYAN, STDOUT),
        'success' => array('✓ ', Colors::C_GREEN, STDOUT),
        'warning' => array('⚠ ', Colors::C_BROWN, STDERR),
        'error' => array('✗ ', Colors::C_RED, STDERR),
        'critical' => array('☠ ', Colors::C_LIGHTRED, STDERR),
        'alert' => array('✖ ', Colors::C_LIGHTRED, STDERR),
        'emergency' => array('✘ ', Colors::C_LIGHTRED, STDERR),
    );

    protected $logdefault = 'info';

    /**
     * constructor
     *
     * Initialize the arguments, set up helper classes and set up the CLI environment
     *     
     * @param bool $autocatch should exceptions be catched and handled automatically?
     * @param 
     */
    public function __construct($autocatch = true, $args = null)
    {
        if ($autocatch) {
            set_exception_handler(array($this, 'fatal'));
        }

        $this->colors = new Colors();
        $this->options = new Options($this->colors, $args);
    }

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     */
    abstract protected function setup(Options $options);

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    abstract protected function main(Options $options);
    
    /**
     * Execute the CLI program
     *
     * Executes the setup() routine, adds default options, initiate the options parsing and argument checking
     * and finally executes main()
     * 
     * Returns result from main() 
     * 
     * @return mixed
     */
    public function run()
    {
        if ('cli' != php_sapi_name()) {
            throw new Exception('This has to be run from the command line');
        }

        // setup
        $this->setup($this->options);
        $this->options->registerOption(
            'help',
            'Display this help screen and exit immeadiately.',
            'h'
        );
        $this->options->registerOption(
            'no-colors',
            'Do not use any colors in output. Useful when piping output to other tools or files.'
        );
        $this->options->registerOption(
            'loglevel',
            'Minimum level of messages to display. Default is ' . $this->colors->wrap($this->logdefault, Colors::C_CYAN) . '. ' .
            'Valid levels are: debug, info, notice, success, warning, error, critical, alert, emergency.',
            null,
            'level'
        );

        // parse
        $this->options->parseOptions();

        // handle defaults
        if ($this->options->getOpt('no-colors')) {
            $this->colors->disable();
        }
        if ($this->options->getOpt('help')) {
            echo $this->options->help();
            exit(0);
        }
        $level = $this->options->getOpt('loglevel', $this->logdefault);
        if (!isset($this->loglevel[$level])) $this->fatal('Unknown log level');
        foreach (array_keys($this->loglevel) as $l) {
            if ($l == $level) break;
            unset($this->loglevel[$l]);
        }

        // check arguments
        $this->options->checkArguments();

        // execute
        return $this->main($this->options);
    }

    // region logging

    /**
     * Exits the program on a fatal error
     *
     * @param \Exception|string $error either an exception or an error message
     * @param array $context
     */
    public function fatal($error, array $context = array())
    {
        $code = 0;
        if (is_object($error) && is_a($error, 'Exception')) {
            /** @var Exception $error */
            $this->debug(get_class($error) . ' caught in ' . $error->getFile() . ':' . $error->getLine());
            $this->debug($error->getTraceAsString());
            $code = $error->getCode();
            $error = $error->getMessage();

        }
        if (!$code) {
            $code = Exception::E_ANY;
        }

        $this->critical($error, $context);
        exit($code);
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function emergency($message, array $context = array())
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     */
    public function alert($message, array $context = array())
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     */
    public function critical($message, array $context = array())
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context = array())
    {
        $this->log('error', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, array $context = array())
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Normal, positive outcome
     *
     * @param string $string
     * @param array $context
     */
    public function success($string, array $context = array())
    {
        $this->log('success', $string, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     */
    public function notice($message, array $context = array())
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, array $context = array())
    {
        $this->log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     */
    public function debug($message, array $context = array())
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        // is this log level wanted?
        if (!isset($this->loglevel[$level])) return;

        /** @var string $prefix */
        /** @var string $color */
        /** @var resource $channel */
        list($prefix, $color, $channel) = $this->loglevel[$level];
        if(!$this->colors->isEnabled()) $prefix = '';

        $message = $this->interpolate($message, $context);
        $this->colors->ptln($prefix . $message, $color, $channel);
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param $message
     * @param array $context
     * @return string
     */
    function interpolate($message, array $context = array())
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    // endregion
}
