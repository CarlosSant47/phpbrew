<?php
namespace PhpBrew\Command;

use CLIFramework\Command;
use PhpBrew\Config;
use PhpBrew\CommandBuilder;
use PhpBrew\BuildFinder;

class InstallLocalVersionCommand extends Command {

    private string $phpVersion;
    private string $prefix = '-local';

    public function brief() {
        return 'Add Local Installed PHP Version (Installed from Brew, Apt, Dnf, etc)';
    }

    public function usage()
    {
        return 'phpbrew install-local-version [php-path]';
    }

    public function aliases()
    {
        return array('il', 'local-link');
    }

    public function arguments($args) {
        $args->add('path')->suggestions(function () {

        });
    }
 

    public function execute($path) {
        if(!$this->validatePathBinaries($path)) {
            return;
        }
        $this->createSymbolicFolder($path);
        
    }

    private function createSymbolicFolder($path) {
        $folderSymlink = 'php-' . $this->phpVersion . $this->prefix;
        $pathInstallations = Config::getRoot() . DIRECTORY_SEPARATOR . 'php';
        $pathVersion = $pathInstallations . DIRECTORY_SEPARATOR . $folderSymlink;
        if(file_exists($pathVersion)) {
            $this->logger->error("This version php is already installed, rename the folder or remove it.");
            return;
        }
        $cmd = new CommandBuilder("ln -s $path $pathVersion");
        $cmd->setAppendLog(false);
        if($cmd->execute() !== 0) {
            $this->logger->error("Error creating symbolic link of the PHP version $this->phpVersion");
            return;
        }
        $this->logger->log("PHP version $this->phpVersion installed successfully. use 'phpbrew use $folderSymlink' to switch to this version. or 'phpbrew switch $folderSymlink' to set as default.");
    }

    private function validatePathBinaries($path): bool {
        if(!file_exists($path)) {
            $this->logger->error("The path $path does not exist.");
            return false;
        }

        if(!is_dir($path)) {
            $this->logger->error("The path $path is not a directory.");
            return false;
        }

        if(!BuildFinder::validatePathVersion($path)) {
            $this->logger->error("The path $path does not contain a PHP binary.");
            return false;
        }
        return $this->getPhpVersionExecutable($path);
    }

    private function getPhpVersionExecutable($path): bool {
        $this->logger->log("================== Validating PHP Version ==================");
        $cmd = new CommandBuilder(escapeshellcmd($path . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php'));
        $cmd->setAppendLog(false);
        $cmd->addArg("-v");

        ob_start();
        system($cmd->__toString(), $returnVar);
        $output = ob_get_clean();

        if ($returnVar !== 0) { // Verifica si el comando fue exitoso
            $this->logger->error("The path $path does not contain a valid PHP binary.");
            return false;
        }
        $this->logger->info($output);
        
        if (preg_match('/PHP\s+([\d\.]+)/', $output, $matches)) {
            $version = $matches[1];
            $this->logger->warn("PHP version $version found.");
            $this->phpVersion = $version;
            return true;
        } else {
            $this->logger->error("Unable to extract PHP version from the output.");
            return false;
        }
    }
}