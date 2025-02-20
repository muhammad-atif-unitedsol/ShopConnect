<?php
namespace Magento\Developer\Console\Command\TemplateHintsDisableCommand;

/**
 * Interceptor class for @see \Magento\Developer\Console\Command\TemplateHintsDisableCommand
 */
class Interceptor extends \Magento\Developer\Console\Command\TemplateHintsDisableCommand implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Framework\App\Config\ConfigResource\ConfigInterface $resourceConfig)
    {
        $this->___init();
        parent::__construct($resourceConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function run(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) : int
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'run');
        return $pluginInfo ? $this->___callPlugins('run', func_get_args(), $pluginInfo) : parent::run($input, $output);
    }
}
