<?php
namespace Causal\Docst3o\Task;

define('LF', "\n");

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Xavier Perseguers <xavier@causal.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Scheduler task to render documentation from queue.
 *
 * @author Xavier Perseguers <xavier@causal.ch>
 */
class RenderTask
{

    const SIZE_THRESHOLD = 1500;    // bytes, about 2 paragraphs of "Lorem Ipsum"

    const DOCUMENTATION_TYPE_UNKNOWN = 0;
    const DOCUMENTATION_TYPE_SPHINX = 1;
    const DOCUMENTATION_TYPE_README = 2;
    const DOCUMENTATION_TYPE_OPENOFFICE = 3;
    const DOCUMENTATION_TYPE_MARKDOWN = 4;

    /**
     * Runs this task.
     *
     * @return void
     */
    public function run()
    {
        $queueDirectory = rtrim($GLOBALS['CONFIG']['DIR']['work'], '/') . '/queue/';
        $renderDirectory = rtrim($GLOBALS['CONFIG']['DIR']['work'], '/') . '/render/';
        $extensionKeys = $this->get_dirs($queueDirectory);

        foreach ($extensionKeys as $extensionKey) {
            $extensionDirectory = $queueDirectory . $extensionKey . '/';
            $versions = $this->get_dirs($extensionDirectory);

            if (!count($versions)) {
                echo '   [INFO] No version found for ' . $extensionKey . ': removing from queue' . LF;
                exec('rm -rf ' . $extensionDirectory);
                continue;
            }

            $basePublishDirectory = rtrim($GLOBALS['CONFIG']['DIR']['publish'], '/') . '/' . $extensionKey . '/';

            foreach ($versions as $version) {
                echo '   [INFO] Processing ' . $extensionKey . ' v.' . $version . LF;
                $versionDirectory = $extensionDirectory . $version . '/';
                $publishDirectory = $basePublishDirectory . $version;

                if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                    if (is_file($versionDirectory . 'Documentation/Index.rst')) {
                        $documentationType = static::DOCUMENTATION_TYPE_SPHINX;

                        if (is_file($versionDirectory . 'Documentation/_Fr/UserManual.rst')) {
                            // This is most probably a garbage documentation coming from the old
                            // documentation template and automatically included with the Extension Builder
                            echo '[WARNING] Garbage documentation from template found: skipping rendering' . LF;
                            $documentationType = static::DOCUMENTATION_TYPE_UNKNOWN;
                        }
                    } elseif (is_file($versionDirectory . 'README.rst') && filesize($versionDirectory . 'README.rst') > static::SIZE_THRESHOLD) {
                        $documentationType = static::DOCUMENTATION_TYPE_README;
                    } elseif (is_file($versionDirectory . 'README.md') && filesize($versionDirectory . 'README.md') > static::SIZE_THRESHOLD && !empty($GLOBALS['CONFIG']['BIN']['pandoc'])) {
                        $documentationType = static::DOCUMENTATION_TYPE_MARKDOWN;
                    } elseif (is_file($versionDirectory . 'doc/manual.sxw')) {
                        $documentationType = static::DOCUMENTATION_TYPE_OPENOFFICE;
                    } else {
                        $documentationType = static::DOCUMENTATION_TYPE_UNKNOWN;
                    }

                    switch ($documentationType) {

                        // ---------------------------------
                        // Sphinx documentation
                        // ---------------------------------
                        case static::DOCUMENTATION_TYPE_SPHINX:
                            $this->renderSphinxProject($extensionKey, $version, $versionDirectory, $renderDirectory, $publishDirectory, $documentationType);
                            break;

                        // ---------------------------------
                        // README.rst documentation
                        // ---------------------------------
                        case static::DOCUMENTATION_TYPE_README:
                            $this->renderReadmeRst($extensionKey, $version, $versionDirectory, $renderDirectory, $publishDirectory, $documentationType);
                            break;

                        // ---------------------------------
                        // README.md documentation
                        // ---------------------------------
                        case static::DOCUMENTATION_TYPE_MARKDOWN:
                            $success = $this->convertReadmeMd($extensionKey, $version, $versionDirectory);
                            if ($success) {
                                $this->renderReadmeRst($extensionKey, $version, $versionDirectory, $renderDirectory, $publishDirectory, $documentationType);
                            }
                            break;

                        // ---------------------------------
                        // OpenOffice documentation
                        // ---------------------------------
                        case static::DOCUMENTATION_TYPE_OPENOFFICE:
                            $success = $this->convertManualSxw($extensionKey, $version, $versionDirectory);
                            if ($success) {
                                $this->renderReadmeRst($extensionKey, $version, $versionDirectory, $renderDirectory, $publishDirectory, $documentationType);
                            }
                            break;

                        default:
                            echo '[WARNING] Unknown documentation format: skipping rendering' . LF;
                            break;
                    }
                }

                $this->removeFromQueue($extensionKey, $version);
                sleep(5);
            }

            /*
            // Put .htaccess for the extension if needed
            if (is_dir($baseBuildDirectory) && !is_file($baseBuildDirectory . '.htaccess')) {
                symlink(rtrim($GLOBALS['CONFIG']['DIR']['scripts'], '/') . '/config/_htaccess', $baseBuildDirectory . '.htaccess');
            }
            */

        }

    }

    /**
     * Renders a Sphinx project.
     *
     * @param string $extensionKey Extension
     * @param string $version Version of the extension
     * @param string $versionDirectory Root directory for the $extensionKey / $version pair
     * @param string $renderDirectory Temporary directory used to render the documentation
     * @param string $publishDirectory Publish directory where rendered documentation will be copied to
     * @param int $documentationType
     * @return bool true if render succeeded, otherwise false
     */
    protected function renderSphinxProject($extensionKey, $version, $versionDirectory, $renderDirectory, $publishDirectory, $documentationType)
    {
        $success = false;
        echo ' [RENDER] ' . $extensionKey . ' ' . $version . ' (Sphinx project)' . LF;

        // Clean-up render directory
        $this->cleanUpDirectory($renderDirectory);

        if (!is_file($versionDirectory . 'Documentation/Settings.yml')) {
            $this->createSettingsYml($versionDirectory, $extensionKey);
        }

        // Fix version/release in Settings.yml
        $this->overrideVersionAndReleaseInSettingsYml($versionDirectory, $version);

        $this->createConfPy(
            $extensionKey,
            $version,
            $renderDirectory,
            'Documentation/'
        );

        $this->createCronRebuildConf(
            $extensionKey,
            $version,
            $publishDirectory,
            $renderDirectory,
            $versionDirectory,
            'Documentation/'
        );

        $this->renderProject($renderDirectory);
        if (!is_file($publishDirectory . '/Index.html')) {
            echo '[WARNING] Cannot find file ' . $publishDirectory . '/Index.html' . LF;
        } else {
            $this->addReference($extensionKey, $documentationType, $version, $versionDirectory, $publishDirectory);
            $this->updateListOfExtensions($extensionKey, $publishDirectory);
            $success = true;
        }

        return $success;
    }

    /**
     * Renders a README.rst file.
     *
     * @param string $extensionKey Extension
     * @param string $version Version of the extension
     * @param string $versionDirectory Root directory for the $extensionKey / $version pair
     * @param string $renderDirectory Temporary directory used to render the documentation
     * @param string $publishDirectory Publish directory where rendered documentation will be copied to
     * @param int $documentationType
     * @return bool true if render succeeded, otherwise false
     */
    protected function renderReadmeRst($extensionKey, $version, $versionDirectory, $renderDirectory, $publishDirectory, $documentationType)
    {
        $success = false;
        echo ' [RENDER] ' . $extensionKey . ' ' . $version . ' (simple README)' . LF;

        // Clean-up render directory
        $this->cleanUpDirectory($renderDirectory);

        $this->createConfPy(
            $extensionKey,
            $version,
            $renderDirectory,
            '',
            'README'
        );

        $this->createCronRebuildConf(
            $extensionKey,
            $version,
            $publishDirectory,
            $renderDirectory,
            $versionDirectory,
            ''
        );

        // We lack a Settings.yml file
        $this->createSettingsYml($versionDirectory, $extensionKey);

        // Fix version/release in Settings.yml
        $this->overrideVersionAndReleaseInSettingsYml($versionDirectory, $version);

        $this->renderProject($renderDirectory);
        if (!is_file($publishDirectory . '/Index.html')) {
            echo '[WARNING] Cannot find file ' . $publishDirectory . '/Index.html' . LF;
        } else {
            $this->addReference($extensionKey, $documentationType, $version, $versionDirectory, $publishDirectory);
            $this->updateListOfExtensions($extensionKey, $publishDirectory);
            $success = true;
        }

        return $success;
    }

    /**
     * Converts a README.md file to reStructuredText format (README.rst in same directory).
     *
     * @param string $extensionKey Extension
     * @param string $version Version of the extension
     * @param string $versionDirectory Root directory for the $extensionKey / $version pair
     * @return bool true if conversion succeeded, otherwise false
     */
    protected function convertReadmeMd($extensionKey, $version, $versionDirectory)
    {
        echo '[CONVERT] ' . $extensionKey . ' ' . $version . ' (Markdown)' . LF;

        $cmd = $GLOBALS['CONFIG']['BIN']['pandoc'] .
            ' -f markdown -t rst' .
            ' -o ' . escapeshellarg($versionDirectory . 'README.rst') .
            ' ' . escapeshellarg($versionDirectory . 'README.md');
        $output = array();
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * "Converts" a manual.sxw file to a simple README.rst file.
     *
     * @param string $extensionKey Extension
     * @param string $version Version of the extension
     * @param string $versionDirectory Root directory for the $extensionKey / $version pair
     * @return bool true if conversion succeeded, otherwise false
     */
    protected function convertManualSxw($extensionKey, $version, $versionDirectory)
    {
        echo '[CONVERT] ' . $extensionKey . ' ' . $version . ' (OpenOffice)' . LF;

        // Create a simple file README.rst
        $title = $extensionKey . ' v' . $version;
        $underline = str_repeat('=', strlen($title));
        $contents = <<<REST
$underline
$title
$underline

.. admonition:: Oh my! That's too bad!
	:class: warning

	Unfortunately the author of $extensionKey is still relying on an outdated
	OpenOffice extension manual instead of providing a Sphinx-based documentation.

	After years of support, the TYPO3 documentation team finally discontinued
	supporting this legacy format of extension manual.

	We are sorry for the inconvenience.


Converting OpenOffice to Sphinx
-------------------------------

It is now due time for the extension author to live in the present century
and switch to Sphinx instead of relying on OpenOffice.

The TYPO3 documentation team encourages the extension author to use the
built-in OpenOffice to Sphinx converter available with EXT:sphinx, available
on `https://typo3.org/extensions/repository/view/sphinx <https://typo3.org/extensions/repository/view/sphinx>`__.


Advantages of using Sphinx
--------------------------

They are numerous advantages of the Sphinx documentation format:

- **Output formats:** Sphinx projects may be automatically rendered as HTML or
  TYPO3-branded PDF.
- **Cross-references:** It is easy to cross-reference other chapters and sections
  of other manuals (either TYPO3 references or extension manuals).
- **Multilingual:** Unlike OpenOffice, Sphinx projects may be easily localized
  and automatically presented in the most appropriate language to TYPO3 users.
- **Collaboration:** As the documentation is plain text, it is easy to work as
  a team on the same manual or quickly review changes using any versioning system.

Please read https://docs.typo3.org/typo3cms/CoreApiReference/ExtensionArchitecture/Documentation/Index.html
for more information on how adding documentation to a TYPO3 extension projet.

Kind Regards

For the documentation team:

| Xavier Perseguers
| TYPO3 CMS Team
|
| TYPO3 ... inspiring people to share
| Get involved: https://typo3.org
|

REST;
        file_put_contents($versionDirectory . 'README.rst', $contents);

        return true;
    }

    /**
     * Overrides the version and release in Settings.yml (because developers simply tend to forget about
     * adapting this info prior to uploading their extension to TER).
     *
     * @param string $path
     * @param string $version
     * @return void
     */
    protected function overrideVersionAndReleaseInSettingsYml($path, $version)
    {
        $path = rtrim($path, '/') . '/';
        if (is_dir($path . 'Documentation')) {
            $filenames = array('Documentation/Settings.yml');

            // Search for other translated versions of Settings.yml
            $directories = $this->get_dirs($path . 'Documentation/');
            foreach ($directories as $directory) {
                if (preg_match('/^Localization\./', $directory)) {
                    $localizationDirectory = $path . 'Documentation/' . $directory . '/Settings.yml';
                    if (!is_file($localizationDirectory)) {
                        copy($path . 'Documentation/Settings.yml', $localizationDirectory);
                    }
                    $filenames[] = 'Documentation/' . $directory . '/Settings.yml';
                }
            }
        } else {
            $filenames = array('Settings.yml');
        }

        // release is actually the "version" from TER
        $release = $version;
        // whereas version is a two digit alternative of the release number
        $parts = explode('.', $release);
        $version = $parts[0] . '.' . $parts[1];

        foreach ($filenames as $filename) {
            $contents = file_get_contents($path . $filename);
            $contents = preg_replace('/^(\s+version): (.*)/m', '\1: ' . $version, $contents);
            $contents = preg_replace('/^(\s+release): (.*)$/m', '\1: ' . $release, $contents);
            file_put_contents($path . $filename, $contents);
        }
    }

    /**
     * Creates a default Settings.yml configuration file.
     *
     * @param string $extensionDirectory
     * @param string $extensionKey
     * @return void
     */
    protected function createSettingsYml($extensionDirectory, $extensionKey)
    {
        $extensionDirectory = rtrim($extensionDirectory, '/') . '/';

        $_EXTKEY = $extensionKey;
        $EM_CONF = array();
        include($extensionDirectory . 'ext_emconf.php');
        $copyright = date('Y');
        $title = $EM_CONF[$_EXTKEY]['title'];

        $configuration = <<<YAML
# This is the project specific Settings.yml file.
# Place Sphinx specific build information here.
# Settings given here will replace the settings of 'conf.py'.

---
conf.py:
  copyright: $copyright
  project: $title
  version: 1.0
  release: 1.0.0
...

YAML;
        $targetDirectory = is_dir($extensionDirectory . 'Documentation') ? $extensionDirectory . 'Documentation' : $extensionDirectory;
        file_put_contents($targetDirectory . '/Settings.yml', $configuration);
    }

    /**
     * Creates a conf.py configuration file.
     *
     * @param string $extensionKey
     * @param string $version
     * @param string $renderDirectory
     * @param string $prefix Optional prefix directory ("Documentation/")
     * @param string $masterDocument
     * @return void
     */
    protected function createConfPy($extensionKey, $version, $renderDirectory, $prefix, $masterDocument = 'Index')
    {
        $replacements = array(
            '###DOCUMENTATION_RELPATH###' => '../queue/' . $extensionKey . '/' . $version . '/' . $prefix,
            '###MASTER_DOC###' => $masterDocument,
        );
        $contents = file_get_contents(dirname(__FILE__) . '/../../etc/conf.py');
        $contents = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        );
        file_put_contents($renderDirectory . 'conf.py', $contents);
    }

    /**
     * Creates a cron_rebuild.conf configuration file.
     *
     * @param string $extensionKey
     * @param string $version
     * @param string $buildDirectory
     * @param string $renderDirectory
     * @param string $versionDirectory
     * @param string $prefix Optional prefix directory ("Documentation/")
     * @param boolean $createArchive
     * @return void
     */
    protected function createCronRebuildConf($extensionKey, $version, $buildDirectory, $renderDirectory, $versionDirectory, $prefix, $createArchive = TRUE)
    {
        $packageZip = $createArchive ? '1' : '0';

        $contents = <<<EOT
PROJECT=$extensionKey
VERSION=$version
TER_EXTENSION=1

# Where to publish documentation
BUILDDIR=$buildDirectory

# If GITURL is empty then GITDIR is expected to be "ready" to be processed
GITURL=
GITDIR=$renderDirectory
GITBRANCH=

# Path to the documentation within the Git repository
T3DOCDIR=${versionDirectory}${prefix}

# Packaging information
PACKAGE_ZIP=$packageZip
PACKAGE_KEY=typo3cms.extensions.$extensionKey
PACKAGE_LANGUAGE=default
EOT;

        file_put_contents($renderDirectory . 'cron_rebuild.conf', $contents);
    }

    /**
     * Renders a Sphinx project.
     *
     * @param string $renderDirectory
     * @return void
     */
    protected function renderProject($renderDirectory)
    {
        $renderDirectory = rtrim($renderDirectory, '/') . '/';

        // [START] Convert Settings.yml as standard Python instructions
        // Since we do not support Yaml configuration file currently...
        $contents = file_get_contents($renderDirectory . 'cron_rebuild.conf');
        $settingsYamlFileName = '';
        $pythonLines = array();
        $lines = explode(LF, $contents);
        foreach ($lines as $line) {
            if (preg_match('/^T3DOCDIR\\s*=\\s*(.+)\s*$/', $line, $matches)) {
                $settingsYamlFileName = rtrim($matches[1], '/') . '/Settings.yml';
                $pythonLines = static::yamlToPython($settingsYamlFileName);
                break;
            }
        }
        if ($pythonLines) {
            $contents = file_get_contents($renderDirectory . 'conf.py');
            $contents .= LF . LF . implode(LF, $pythonLines);
            file_put_contents($renderDirectory . 'conf.py', $contents);
            // Legacy from cron_rebuild.sh to detect PDF output
            copy($settingsYamlFileName, $renderDirectory . '10+20+30_conf_py.yml');
        }
        // [END] Convert Settings.yml as standard Pythno instructions

        symlink(rtrim($GLOBALS['CONFIG']['DIR']['etc'], '/') . '/Makefile', $renderDirectory . 'Makefile');
        symlink(rtrim($GLOBALS['CONFIG']['DIR']['scripts'], '/') . '/cron_rebuild.sh', $renderDirectory . 'cron_rebuild.sh');

        // Invoke rendering
        $cmd = 'cd ' . $renderDirectory . ' && touch REBUILD_REQUESTED && ./cron_rebuild.sh';
        exec($cmd);

        // TODO? Copy warnings*.txt + possible pdflatex log to output directory
    }

    /**
     * Adds a reference to the documentation (e.g., used by EXT:sphinx).
     *
     * @param string $extensionKey
     * @param string $format
     * @param string $version
     * @param string $extensionDirectory
     * @param string $buildDirectory
     * @return void
     */
    protected function addReference($extensionKey, $format, $version, $extensionDirectory, $buildDirectory)
    {
        $extensionDirectory = rtrim($extensionDirectory, '/') . '/';
        $buildDirectory = rtrim($buildDirectory, '/');    // No trailing slash here!
        $referenceFilename = rtrim($GLOBALS['CONFIG']['DIR']['publish'], '/') . '/manuals.json';
        $references = array();
        if (is_file($referenceFilename)) {
            $references = json_decode(file_get_contents($referenceFilename), TRUE);
            if (!is_array($references)) {
                $references = array();
            }
        }

        $references[$extensionKey] = array(
            'lastupdated' => time(),
            'format' => $format,
            'version' => $version,
        );

        if ($format == static::DOCUMENTATION_TYPE_SPHINX) {
            if (count(glob($buildDirectory . '/_pdf/*.pdf')) > 0) {
                $references[$extensionKey]['pdf'][] = 'default';
            }

            $directories = $this->get_dirs($extensionDirectory . 'Documentation/');
            foreach ($directories as $directory) {
                if (preg_match('/^Localization\.(.*)/', $directory, $matches)) {
                    $locale = str_replace('_', '-', strtolower($matches[1]));
                    $version = basename($buildDirectory);
                    $localeDirectory = $buildDirectory . '/../' . $locale . '/' . $version . '/';
                    if (is_file($localeDirectory . 'Index.html')) {
                        $references[$extensionKey]['localizations'][] = $matches[1];

                        if (count(glob($localeDirectory . '_pdf/*.pdf')) > 0) {
                            $references[$extensionKey]['pdf'][] = $matches[1];
                        }
                    }
                }
            }
        }

        ksort($references);
        file_put_contents($referenceFilename, json_encode($references));
    }

    /**
     * Updates the list of extensions in "extensions.js".
     *
     * @param string $extensionKey
     * @param string $directory Build directory of the last rendered documentation (thus incl. version number at the end)
     * @return void
     */
    protected function updateListOfExtensions($extensionKey, $directory, $refresh = FALSE)
    {
        $extensionsJsFilename = rtrim($GLOBALS['CONFIG']['DIR']['publish'], '/') . '/extensions.js';
        $extensions = array();
        if (is_file($extensionsJsFilename)) {
            $content = file_get_contents($extensionsJsFilename);
            $declaration = 'var extensionList =';
            // Cut to beginning of JSON string
            $content = trim(substr($content, strpos($content, $declaration) + strlen($declaration)));
            // Cut from end of JSON string (trailing semicolon)
            $content = substr($content, 0, -1);
            if ($content{0} === '[') {
                $extensions = json_decode($content, TRUE);
                if ($extensions === NULL) {
                    // Something went wrong, we do not want to further corrupt the file
                    echo '[WARNING] File ' . $extensionsJsFilename . ' cannot be decoded, please investigate and fix it.' . LF;
                    return;
                }
                $numberOfExtensions = count($extensions);
                $list = array();
                for ($i = 0; $i < $numberOfExtensions; $i++) {
                    $list[$extensions[$i]['key']] = $extensions[$i];
                }
                $extensions = $list;
                unset($list);
                if ($refresh) {
                    $exts = array_keys($extensions);
                    foreach ($exts as $ext) {
                        $extDir = rtrim($GLOBALS['CONFIG']['DIR']['publish'], '/') . '/' . $ext;
                        if (is_dir($extDir)) {
                            echo '   [INFO] Refreshing versions of ' . $ext . LF;
                            $this->updateListOfExtensions($ext, $extDir . '/latest');
                        }
                    }
                    return;
                }
            }
        } else {
            // TODO: initialize this file by searching for every existing extensions and versions?
        }
        if (count($extensions) === 0) {
            return;
        }

        $versions = $this->get_dirs(dirname($directory));
        $versions = array_flip($versions);

        // No real versions
        unset($versions['packages']);
        unset($versions['stable']);

        $hasLatest = isset($versions['latest']);
        unset($versions['latest']);
        $versions = array_flip($versions);

        // Remove localizations
        for ($i = 0; $i < count($versions); $i++) {
            if (!preg_match('/^[0-9]/', $versions[$i])) {
                unset($versions[$i]);
            }
        }

        // Reverse sort the list of versions
        usort($versions, function ($a, $b) {
            return version_compare($b, $a);
        });

        if ($hasLatest) {
            array_unshift($versions, 'latest');
        }

        $extensions[$extensionKey] = array(
            'key' => $extensionKey,
            'latest' => $versions[0],
            'versions' => $versions,
        );

        // Sort by extension key
        ksort($extensions);

        $content = '// BEWARE: this file has been automatically generated by ' . __CLASS__ . LF;
        $content .= '// on ' . date('d.m.Y H:i:s') . LF;
        $content .= '// DO NOT MODIFY MANUALLY' . LF;
        $content .= $declaration . ' ';

        $json = json_encode(array_values($extensions));
        // Prettify a bit the JSON (without making it too verbose if we would use built-in feature of PHP 5.4)
        $json = "[\n\t" . substr($json, 1, -1) . "\n]";
        $json = str_replace('},', "},\n\t", $json);

        $content .= $json . ';';

        file_put_contents($extensionsJsFilename, $content);
    }

    /**
     * Cleans-up a directory.
     *
     * @param string $path
     * @return void
     */
    protected function cleanUpDirectory($path)
    {
        exec('rm -rf ' . escapeshellarg($path));
        exec('mkdir -p ' . escapeshellarg($path));
    }

    /**
     * Removes an extensionKey/version pair from the rendering queue.
     *
     * @param string $extensionKey
     * @param string $version
     * @return void
     */
    protected function removeFromQueue($extensionKey, $version)
    {
        $queueDirectory = rtrim($GLOBALS['CONFIG']['DIR']['work'], '/') . '/queue/';
        $path = $queueDirectory . $extensionKey . '/' . $version;
        exec('rm -rf ' . $path);
    }

    /**
     * Returns an array with the names of folders in a specific path
     * Will return 'error' (string) if there were an error with reading directory content.
     *
     * @param string $path Path to list directories from
     * @return array Returns an array with the directory entries as values.
     * @see \TYPO3\CMS\Core\Utility\GeneralUtility::get_dirs()
     */
    protected function get_dirs($path)
    {
        $dirs = array();
        if ($path) {
            if (is_dir($path)) {
                $dir = scandir($path);
                foreach ($dir as $entry) {
                    if (is_dir($path . '/' . $entry) && $entry != '..' && $entry != '.') {
                        $dirs[] = $entry;
                    }
                }
            } else {
                $dirs = 'error';
            }
        }
        return $dirs;
    }

    /**
     * Converts a (simple) YAML file to Python instructions.
     *
     * Note: First tried to use 3rd party libraries:
     *
     * - spyc: http://code.google.com/p/spyc/
     * - Symfony2 YAML: http://symfony.com/doc/current/components/yaml/introduction.html
     *
     * but none of them were able to parse our Settings.yml Sphinx configuration files.
     *
     * NOTE: This is the exact copy of \Causal\Sphinx\Utility\MiscUtility::yamlToPython()
     *
     * @param string $filename Absolute filename to Settings.yml
     * @return string Python instruction set
     */
    static protected function yamlToPython($filename)
    {
        $contents = file_get_contents($filename);
        $lines = explode(LF, $contents);
        $pythonConfiguration = array();

        // Remove empty lines and comments
        $lines = array_values(array_filter($lines, function ($line) {
            return !(trim($line) === '' || preg_match('/^\\s*#/', $line));
        }));

        $i = 0;
        while ($lines[$i] !== 'conf.py:' && $i < count($lines)) {
            $i++;
        }
        while ($i < count($lines)) {
            if (preg_match('/^(\s+)([^:]+):\s*(.*)$/', $lines[$i], $matches)) {
                switch ($matches[2]) {
                    case 'latex_documents':
                        $pythonLine = 'latex_documents = [(' . LF;
                        if (preg_match('/^(\s+)- - /', $lines[$i + 1], $matches)) {
                            $indent = $matches[1];
                            $firstLine = TRUE;
                            while (preg_match('/^' . $indent . '(- -|  -) (.+)$/', $lines[++$i], $matches)) {
                                if (!$firstLine) {
                                    $pythonLine .= ',' . LF;
                                }
                                $pythonLine .= sprintf('u\'%s\'', addcslashes($matches[2], "\\'"));
                                $firstLine = FALSE;
                            }
                        }
                        $pythonLine .= LF . ')]';
                        $i--;
                        break;
                    case 'latex_elements':
                    case 'html_theme_options':
                        $pythonLine = $matches[2] . ' = {' . LF;
                        if (preg_match('/^(\s+)/', $lines[$i + 1], $matches)) {
                            $indent = $matches[1];
                            $firstLine = TRUE;
                            while (preg_match('/^' . $indent . '([^:]+):\s*(.*)$/', $lines[++$i], $matches)) {
                                if (!$firstLine) {
                                    $pythonLine .= ',' . LF;
                                }
                                $pythonLine .= sprintf('\'%s\': ', $matches[1]);
                                if ($matches[2] === 'null') {
                                    $pythonLine .= 'None';
                                } elseif (/*GeneralUtility*/
                                static::inList('true,false', strtolower($matches[2]))
                                ) {
                                    $pythonLine .= ucfirst($matches[2]);
                                } elseif (/*\TYPO3\CMS\Core\Utility\MathUtility*/
                                static::canBeInterpretedAsInteger($matches[2])
                                ) {
                                    $pythonLine .= intval($matches[2]);
                                } else {
                                    $pythonLine .= sprintf('\'%s\'', addcslashes($matches[2], "\\'"));
                                }
                                $firstLine = FALSE;
                            }
                        }
                        $pythonLine .= LF . '}';
                        $i--;
                        break;
                    case 'extensions':
                        $pythonLine = 'extensions = [';
                        if (preg_match('/^(\s+)/', $lines[$i + 1], $matches)) {
                            $indent = $matches[1];
                            $firstItem = TRUE;
                            while (preg_match('/^' . $indent . '- (.+)/', $lines[++$i], $matches)) {
                                if (/*GeneralUtility*/
                                static::isFirstPartOfStr($matches[1], 't3sphinx.')
                                ) {
                                    // Extension t3sphinx is not compatible with JSON output
                                    continue;
                                }

                                if (!$firstItem) {
                                    $pythonLine .= ', ';
                                }
                                $pythonLine .= sprintf('\'%s\'', addcslashes($matches[1], "\\'"));
                                $firstItem = FALSE;
                            }
                            $i--;
                        }
                        $pythonLine .= ']';
                        break;
                    case 'extlinks':
                    case 'intersphinx_mapping':
                        $pythonLine = $matches[2] . ' = {' . LF;
                        if (preg_match('/^(\s+)/', $lines[$i + 1], $matches)) {
                            $indent = $matches[1];
                            $firstLine = TRUE;
                            while (preg_match('/^' . $indent . '(.+):/', $lines[++$i], $matches)) {
                                if (!$firstLine) {
                                    $pythonLine .= ',' . LF;
                                }
                                $pythonLine .= sprintf('\'%s\': (', $matches[1]);
                                $firstItem = TRUE;
                                while (preg_match('/^' . $indent . '- (.+)/', $lines[++$i], $matches)) {
                                    if (!$firstItem) {
                                        $pythonLine .= ', ';
                                    }
                                    if ($matches[1] === 'null') {
                                        $pythonLine .= 'None';
                                    } else {
                                        $pythonLine .= sprintf('\'%s\'', trim(trim($matches[1]), '\''));
                                    }
                                    $firstItem = FALSE;
                                }
                                $pythonLine .= ')';
                                $firstLine = FALSE;
                                $i--;
                            }
                        }
                        $pythonLine .= LF . '}';
                        $i--;
                        break;
                    case 'copyright':
                    case 'version':
                    case 'release':
                        $pythonLine = sprintf('%s = u\'%s\'', $matches[2], addcslashes($matches[3], "\\'"));
                        break;
                    default:
                        $pythonLine = $matches[2] . ' = ';
                        if ($matches[3] === 'null') {
                            $pythonLine .= 'None';
                        } elseif (/*GeneralUtility*/
                        static::inList('true,false', strtolower($matches[3]))
                        ) {
                            $pythonLine .= ucfirst($matches[3]);
                        } elseif (/*\TYPO3\CMS\Core\Utility\MathUtility*/
                        static::canBeInterpretedAsInteger($matches[3])
                        ) {
                            $pythonLine .= intval($matches[3]);
                        } else {
                            $pythonLine .= sprintf('u\'%s\'', addcslashes($matches[3], "\\'"));
                        }
                        break;
                }
                if (!empty($pythonLine)) {
                    $pythonConfiguration[] = $pythonLine;
                }
            }
            $i++;
        }

        return $pythonConfiguration;
    }

    /**
     * Tests if the input can be interpreted as integer.
     *
     * Note: Integer casting from objects or arrays is considered undefined and thus will return false.
     *
     * @see http://php.net/manual/en/language.types.integer.php#language.types.integer.casting.from-other
     * @param mixed $var Any input variable to test
     * @return bool Returns TRUE if string is an integer
     */
    static protected function canBeInterpretedAsInteger($var)
    {
        if ($var === '' || is_object($var) || is_array($var)) {
            return FALSE;
        }
        return (string)(int)$var === (string)$var;
    }

    /**
     * Check for item in list
     * Check if an item exists in a comma-separated list of items.
     *
     * @param string $list Comma-separated list of items (string)
     * @param string $item Item to check for
     * @return bool TRUE if $item is in $list
     */
    static protected function inList($list, $item)
    {
        return strpos(',' . $list . ',', ',' . $item . ',') !== FALSE;
    }

    /**
     * Returns TRUE if the first part of $str matches the string $partStr
     *
     * @param string $str Full string to check
     * @param string $partStr Reference string which must be found as the "first part" of the full string
     * @return bool TRUE if $partStr was found to be equal to the first part of $str
     */
    static protected function isFirstPartOfStr($str, $partStr)
    {
        return $partStr != '' && strpos((string)$str, (string)$partStr, 0) === 0;
    }

}

$GLOBALS['CONFIG'] = require_once(dirname(__FILE__) . '/../../etc/LocalConfiguration.php');

$task = new RenderTask();
$task->run();
