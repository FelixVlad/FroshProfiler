<?php

namespace FroshProfiler;

use Doctrine\ORM\Tools\SchemaTool;
use FroshProfiler\Components\Tracleable\TraceableCompilerPass;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use FroshProfiler\Components\CompilerPass\AddTemplatePluginDirCompilerPass;
use FroshProfiler\Components\CompilerPass\CustomCacheCompilerPass;
use FroshProfiler\Components\CompilerPass\CustomEventManagerCompilerPass;
use FroshProfiler\Components\CompilerPass\ProfilerCollectorCompilerPass;
use FroshProfiler\Models\Profile;
use Symfony\Component\ClassLoader\Psr4ClassLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

global $kernel;

if (method_exists($kernel, 'getCacheDir')) {
    $loader = new Psr4ClassLoader();
    $loader->addPrefix('FroshProfilerProxy', dirname($kernel->getCacheDir()) . '/tracer/FroshProfilerProxy/');
    $loader->register();
}

/**
 * Class FroshProfiler
 */
class FroshProfiler extends Plugin
{
    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $this->installSchema();
    }

    /**
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context)
    {
        parent::update($context);
        $this->installSchema();
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        parent::uninstall($context);
        $this->uninstallSchema();
    }

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('frosh_profiler.plugin_dir', $this->getPath());

        parent::build($container);

        $container->addCompilerPass(new CustomEventManagerCompilerPass());
        $container->addCompilerPass(new ProfilerCollectorCompilerPass());
        $container->addCompilerPass(new AddTemplatePluginDirCompilerPass());
        $container->addCompilerPass(new CustomCacheCompilerPass());
        $container->addCompilerPass(new TraceableCompilerPass());
    }

    /**
     * Install or update profile table
     */
    private function installSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));

        $tool->updateSchema([$this->container->get('models')->getClassMetadata(Profile::class)], true);
    }

    /**
     * Remove profile table
     */
    private function uninstallSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));

        $tool->dropSchema([$this->container->get('models')->getClassMetadata(Profile::class)]);
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Controllers_Frontend_Checkout::saveOrder::replace' => 'replace',
            'Shopware_Controllers_Frontend_Checkout::saveOrder::after' => [
                ['after'],
                ['after2'],
            ]
        ];
    }

    public function after(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Checkout $subject */
        $subject = $args->getSubject();

        echo "After!!";
        $args->stop();
    }

    public function after2(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Checkout $subject */
        $subject = $args->getSubject();

        echo "After!!";
    }

    public function replace(\Enlight_Hook_HookArgs $arguments)
    {
        /** @var \Shopware_Controllers_Frontend_Checkout $subject */
        $subject = $arguments->getSubject();

        $arguments->setReturn(
            $arguments->getSubject()->executeParent(
                $arguments->getMethod(),
                $arguments->getArgs()
            )
        );

        $arguments->stop();

        echo 'Replace';
    }
}
