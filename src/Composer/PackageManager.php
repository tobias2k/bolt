<?php

namespace Bolt\Composer;

use Bolt\Composer\Action\DumpAutoload;
use Bolt\Composer\Action\RemovePackage;
use Bolt\Composer\Action\RequirePackage;
use Bolt\Composer\Action\SearchPackage;
use Bolt\Composer\Action\ShowPackage;
use Bolt\Composer\Action\UpdatePackage;
use Composer\Factory;
use Composer\IO\BufferIO;
use Silex\Application;

class PackageManager
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var Composer\IO\BufferIO
     */
    private $io;

    /**
     * @var Composer\Composer
     */
    private $composer;

    /**
     * @var Bolt\Composer\Action\DumpAutoload
     */
    private $dumpautoload;

    /**
     * @var Bolt\Composer\Action\RemovePackage
     */
    private $remove;

    /**
     * @var Bolt\Composer\Action\RequirePackage
     */
    private $require;

    /**
     * @var Bolt\Composer\Action\SearchPackage
     */
    private $search;

    /**
     * @var Bolt\Composer\Action\ShowPackage
     */
    private $show;

    /**
     * @var Bolt\Composer\Action\UpdatePackage
     */
    private $update;

    /**
     * @var Silex\Application
     */
    private $app;

    /**
     *
     * @param Application $app
     * @param boolean     $readWriteMode
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        // Get default options
        $this->getOptions();

        // Set composer environment variables
        putenv('COMPOSER_HOME=' . $this->app['resources']->getPath('cache') . '/composer');

        if ($app['extend.mode'] === 'online') {
            // Create the IO
            $this->io = new BufferIO();

            // Use the factory to get a new Composer object
            $this->composer = Factory::create($this->io, $this->options['composerjson'], true);

            // Copy/update installer helper
            $this->copyInstaller();
        }
    }

    /**
     * Return the output from the last IO
     *
     * @return array
     */
    public function getOutput()
    {
        return $this->io->getOutput();
    }

    /**
     * Dump fresh autoloader
     */
    public function dumpautoload()
    {
        if (!$this->dumpautoload) {
            $this->dumpautoload = new DumpAutoload($this->io, $this->composer, $this->options);
        }

        $this->dumpautoload->execute();
    }

    /**
     * Remove packages from the root install
     *
     * @param $packages array Indexed array of package names to remove
     * @return integer 0 on success or a positive error code on failure
     */
    public function removePackage(array $packages)
    {
        if (!$this->remove) {
            $this->remove = new RemovePackage($this->io, $this->composer, $this->options);
        }

        // 0 on success or a positive error code on failure
        $status = $this->remove->execute($packages);
    }

    /**
     * Require (install) packages
     *
     * @param $packages array Associative array of package names/versions to remove
     *                        Format: array('name' => '', 'version' => '')
     * @return integer 0 on success or a positive error code on failure
     */
    public function requirePackage(array $packages)
    {
        if (!$this->require) {
            $this->require = new RequirePackage($this->io, $this->composer, $this->options);
        }

        // 0 on success or a positive error code on failure
        $status = $this->require->execute($packages);
    }

    /**
     * Search for packages
     *
     * @param $packages array Indexed array of package names to search
     * @return array List of matching packages
     */
    public function searchPackage(array $packages)
    {
        if (!$this->search) {
            $this->search = new SearchPackage($this->io, $this->composer, $this->options);
        }

        return $this->search->execute($packages);
    }

    /**
     * Show packages
     *
     * @param $packages
     * @return
     */
    public function showPackage($target, $package = '', $version = '')
    {
        if (!$this->show) {
            $this->show = new ShowPackage($this->io, $this->composer, $this->options);
        }

        return $this->show->execute($target, $package, $version);
    }

    /**
     * Remove packages from the root install
     *
     * @param $packages array Indexed array of package names to remove
     * @return integer 0 on success or a positive error code on failure
     */
    public function updatePackage(array $packages)
    {
        if (!$this->update) {
            $this->update = new UpdatePackage($this->io, $this->composer, $this->options);
        }

        // 0 on success or a positive error code on failure
        $status = $this->update->execute($packages);
    }

    /**
     * Install/update extension installer helper
     */
    private function copyInstaller()
    {
        $class = new \ReflectionClass("Bolt\\Composer\\ExtensionInstaller");
        $filename = $class->getFileName();
        copy($filename, $this->basedir . '/installer.php');
    }

    /**
     * Set the default options
     */
    private function getOptions()
    {
        $this->options = array(
            'basedir'                => $this->app['resources']->getPath('extensions'),
            'composerjson'           => $this->app['resources']->getPath('extensions') . 'composer.json',
            'logfile'                => $this->app['resources']->getPath('cachepath') . '/composer_log',

            'dryrun'                 => null,    // dry-run              - Outputs the operations but will not execute anything (implicitly enables --verbose)
            'verbose'                => true,    // verbose              - Shows more details including new commits pulled in when updating packages
            'nodev'                  => null,    // no-dev               - Disables installation of require-dev packages
            'noautoloader'           => null,    // no-autoloader        - Skips autoloader generation
            'noscripts'              => null,    // no-scripts           - Skips the execution of all scripts defined in composer.json file
            'withdependencies'       => true,    // with-dependencies    - Add also all dependencies of whitelisted packages to the whitelist
            'ignoreplatformreqs'     => null,    // ignore-platform-reqs - Ignore platform requirements (php & ext- packages)
            'preferstable'           => null,    // prefer-stable        - Prefer stable versions of dependencies
            'preferlowest'           => null,    // prefer-lowest        - Prefer lowest versions of dependencies

            'sortpackages'           => true,    // sort-packages        - Sorts packages when adding/updating a new dependency
            'prefersource'           => false,   // prefer-source        - Forces installation from package sources when possible, including VCS information
            'preferdist'             => true,    // prefer-dist          - Forces installation from package dist even for dev versions
            'update'                 => true,    // [Custom]             - Do package update as well
            'noupdate'               => null,    // no-update            - Disables the automatic update of the dependencies
            'updatenodev'            => true,    // update-no-dev        - Run the dependency update with the --no-dev option
            'updatewithdependencies' => true,    // update-with-dependencies - Allows inherited dependencies to be updated with explicit dependencies

            'dev'                    => null,    // dev - Add requirement to require-dev
                                                 //       Removes a package from the require-dev section
                                                 //       Disables autoload-dev rules

            'onlyname'              => true,     // only-name - Search only in name

            'optimizeautoloader'    => true,     // optimize-autoloader - Optimizes PSR0 and PSR4 packages to be loaded with classmaps too, good for production.
        );
    }
}
