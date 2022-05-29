<?php

namespace PhpDoxy;

require_once "vendor/autoload.php";

function generate($argv): void
{
    $start = time();
    
    if (php_sapi_name() == "cli") {
        $working_dir = getcwd();
    } else {
        $working_dir = __DIR__;
    }
    
    echo_standard("\n");
    echo_highlighted("Documentation generation started");
    echo_standard("\n");
    
    echo_standard("\nPHP version: " . PHP_VERSION);
    echo_standard("\nWorking directory: " . $working_dir);
    
    try {
        if (!empty($argv[1])) {
            $config_file = $argv[1];
            
            if (!file_exists($config_file)) {
                if (!file_exists($working_dir . DIRECTORY_SEPARATOR . $argv[1])) {
                    throw new \Exception("The config file '$config_file' does not exist!");
                } else {
                    $config_file = $working_dir . DIRECTORY_SEPARATOR . $argv[1];
                }
            }
        } else {
            $config_file = $working_dir . DIRECTORY_SEPARATOR . "phpdoxy_config.xml";
            
            if (!file_exists($config_file)) {
                throw new \Exception("The config file 'phpdoxy_config.xml' was not provided in the working directory '" . $working_dir . "'!");
            }
        }
        
        $config_file = realpath($config_file);
        
        echo_standard("\nConfig file: " . $config_file);
        
        $config = [];
        
        $config_xmldoc = new \DOMDocument("1.0", "UTF-8");
        $config_xmldoc->formatOutput = true;
        if (!$config_xmldoc->load($config_file)) {
            throw new \Exception("Error by XML parsing XML file '$config_file'!");
        }
        
        $xsdpath = new \DOMXPath($config_xmldoc);
        
        $nodes = $xsdpath->evaluate("/config/source");
        if ($nodes->length == 0) {
            throw new \Exception("Source directory is not specified!");
        }
        $orig_source = trim($nodes->item(0)->nodeValue);
        
        do {
            $config["source"] = $orig_source;
            if (file_exists($config["source"])) {
                break;
            }
            
            $config["source"] = dirname($config_file) . DIRECTORY_SEPARATOR . $orig_source;
            if (file_exists($config["source"])) {
                break;
            }
            
            $config["source"] = $working_dir . DIRECTORY_SEPARATOR . $orig_source;
            if (file_exists($config["source"])) {
                break;
            }
            
            throw new \Exception("The source file '" . $orig_source . "' does not exist!");
        } while (false);
        
        $config["source"] = realpath($config["source"]);
        echo_standard("\nSource directory: " . $config["source"]);
        
        $nodes = $xsdpath->evaluate("/config/target");
        if ($nodes->length == 0) {
            throw new \Exception("Target directory is not specified!");
        }
        $config["target"] = trim($nodes->item(0)->nodeValue);
        
        $orig_target_parent = dirname($config["target"]);
        
        do {
            $target_parent = $orig_target_parent;
            if (file_exists($target_parent)) {
                break;
            }
            
            $target_parent = dirname($config_file) . DIRECTORY_SEPARATOR . $orig_target_parent;
            if (file_exists($target_parent)) {
                break;
            }
            
            $target_parent = $working_dir . DIRECTORY_SEPARATOR . $orig_target_parent;
            if (file_exists($target_parent)) {
                break;
            }
            
            throw new \Exception("The target file path '" . $orig_target_parent . "' is invalid or does not exist!");
        } while (false);
        
        if (!file_exists($target_parent . DIRECTORY_SEPARATOR . basename($config["target"]))) {
            if (!mkdir($target_parent . DIRECTORY_SEPARATOR . basename($config["target"]))) {
                throw new \Exception("The target file path '" . $orig_target_parent . "' can not be created!");
            }
        }
        
        $config["target"] = realpath($target_parent . DIRECTORY_SEPARATOR . basename($config["target"]));
        echo_standard("\nTarget directory: " . $config["target"]);
        
        $nodes = $xsdpath->evaluate("/config/template");
        if ($nodes->length == 0) {
            throw new \Exception("Template name is not specified!");
        }
        $orig_template = trim($nodes->item(0)->nodeValue);
        
        do {
            $config["template"] = $orig_template;
            if (file_exists($config["template"])) {
                break;
            }
            
            $config["template"] = dirname($config_file) . DIRECTORY_SEPARATOR . $orig_template;
            if (file_exists($config["template"])) {
                break;
            }
            
            $config["template"] = $working_dir . DIRECTORY_SEPARATOR . $orig_template;
            if (file_exists($config["template"])) {
                break;
            }
            
            $config["template"] = get_templates_dir() . DIRECTORY_SEPARATOR . "../templates/" . $orig_template;
            if (file_exists($config["template"])) {
                break;
            }
            
            throw new \Exception("Template '" . $config["template"] . "' does not exist in the directory '" . realpath(__DIR__ . DIRECTORY_SEPARATOR . "../templates/") . "' or its path is invalid!");
        } while (false);
        
        $config["template"] = realpath($config["template"]);
        echo_standard("\nTemplate: " . $config["template"]);
        
        $nodes = $xsdpath->evaluate("/config/user_files/user_file");
        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $orig_path = trim($nodes->item($i)->nodeValue);
                
                do {
                    $path = $orig_path;
                    if (file_exists($path)) {
                        break;
                    }
                    
                    $path = dirname($config_file) . DIRECTORY_SEPARATOR . $orig_path;
                    if (file_exists($path)) {
                        break;
                    }
                    
                    $path = getcwd() . DIRECTORY_SEPARATOR . $orig_path;
                    if (file_exists($path)) {
                        break;
                    }
                    
                    throw new \Exception("The user source file '" . $orig_path . "' does not exist!");
                } while (false);
                
                $path = realpath($path);
                
                $title = trim($nodes->item($i)->getAttribute("title"));
                if (empty($title)) {
                    $title = basename($path);
                }
                
                $menu_title = trim($nodes->item($i)->getAttribute("menu_title"));
                if (empty($menu_title)) {
                    $menu_title = $title;
                }
                
                $config["user_files"][] = [
                    "title" => $title,
                    "menu_title" => $menu_title,
                    "path" => $path
                ];
            }
        }
        
        $config["title"] = "Library Documentation";
        $nodes = $xsdpath->evaluate("/config/title");
        if ($nodes->length > 0) {
            $config["title"] = trim($nodes->item(0)->nodeValue);
        }
        
        $parser = new PhpDocParser();
        
        $generator = new PhpDocGenerator($parser->process($config));
        
        $generator->generate($config);
    } catch (\Throwable $ex) {
        $error = $ex->getMessage();
        $error .= "\nFile: " . $ex->getFile() . ", line: " . $ex->getLine();
        PhpDocWatcher::trackError($error);
    }
    
    $errors = PhpDocWatcher::hasErrors();
    $warnings = PhpDocWatcher::hasWarnings();
    
    if ($errors) {
        echo_error("\n\n$errors error(s) detected!");
        
        PhpDocWatcher::reportErrors();
        
        echo_error("\n\nDocumentation generation failed with $errors error(s)!");
    } elseif ($warnings) {
        echo_warning("\n\n$warnings warnings(s) detected!");
        
        PhpDocWatcher::reportWarnings();
        
        echo_warning("\n\nDocumentation generation completed with $warnings warning(s), time elapsed: " . (time() - $start) . " second(s)");
    } else {
        echo_success("\n\nDocumentation generation succeeded, time elapsed: " . (time() - $start) . " second(s)");
    }
    
    echo_standard("\n");
} // generate
