<?php
namespace Causal\Docst3o;

define('LF', "\n");

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Xavier Perseguers <xavier@causal.ch>
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
 * Yaml to Python converter.
 *
 * @author Xavier Perseguers <xavier@causal.ch>
 */
class Yaml2Python
{

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
    public static function convert($filename)
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
    protected static function canBeInterpretedAsInteger($var)
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
    protected static function inList($list, $item)
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
    protected static function isFirstPartOfStr($str, $partStr)
    {
        return $partStr != '' && strpos((string)$str, (string)$partStr, 0) === 0;
    }

}

if (isset($argv[0])) {
    if (!isset($argv[1])) {
        echo 'Usage: ' . $argv[0] . ' /path/to/Settings.yml' . LF;
        exit(1);
    }

    $pythonConfiguration = \Causal\Docst3o\Yaml2Python::convert($argv[1]);
    echo LF . LF . implode(LF, $pythonConfiguration) . LF;
}
