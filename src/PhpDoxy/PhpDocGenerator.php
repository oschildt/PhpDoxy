<?php

namespace PhpDoxy;

class PhpDocGenerator
{
    protected $dictionary;
    
    protected $config;
    protected $target_dir;
    protected $gen_date;
    
    public function __construct(&$dictionary)
    {
        $this->dictionary = &$dictionary;
    } // __construct
    
    protected function delDir($dir)
    {
        if (!$dh = @opendir($dir)) {
            throw new \Exception("Error by deleting directory: " . $dir);
        }
        
        while ($obj = readdir($dh)) {
            if ($obj == '.' || $obj == '..') {
                continue;
            }
            
            if (is_dir($dir . DIRECTORY_SEPARATOR . $obj)) {
                try {
                    $this->delDir($dir . DIRECTORY_SEPARATOR . $obj);
                } catch (\Exception $ex) {
                    @closedir($dh);
                    throw $ex;
                }
            } else {
                if (!@unlink($dir . DIRECTORY_SEPARATOR . $obj)) {
                    @closedir($dh);
                    throw new \Exception("Error by deleting file: " . $dir . DIRECTORY_SEPARATOR . $obj);
                }
            }
        } // while
        
        @closedir($dh);
        
        if (!@rmdir($dir)) {
            throw new \Exception("Error by deleting directory: " . $dir);
        }
    } // delDir
    
    protected function clearDir($dir)
    {
        if (!$dh = @opendir($dir)) {
            throw new \Exception("Error by clearing directory: " . $dir);
        }
        
        while ($obj = readdir($dh)) {
            if ($obj == '.' || $obj == '..') {
                continue;
            }
            
            if (is_dir($dir . DIRECTORY_SEPARATOR . $obj)) {
                try {
                    $this->delDir($dir . DIRECTORY_SEPARATOR . $obj);
                } catch (\Exception $ex) {
                    @closedir($dh);
                    throw $ex;
                }
            } else {
                if (!@unlink($dir . DIRECTORY_SEPARATOR . $obj)) {
                    @closedir($dh);
                    throw new \Exception("Error by deleting file: " . $dir . DIRECTORY_SEPARATOR . $obj);
                }
            }
        } // while
        
        @closedir($dh);
    } // clearDir
    
    protected function copyDirContents($source_dir, $target_dir)
    {
        if (!file_exists($target_dir) || !is_dir($target_dir)) {
            throw new \Exception("Target dir '$target_dir' does not exist or is not a directory!");
        }
        
        if (!$dh = @opendir($source_dir)) {
            throw new \Exception("Error by copying from directory: " . $source_dir);
        }
        
        while ($obj = readdir($dh)) {
            if ($obj == '.' || $obj == '..') {
                continue;
            }
            
            if (is_dir($source_dir . DIRECTORY_SEPARATOR . $obj)) {
                if (!file_exists($target_dir . DIRECTORY_SEPARATOR . $obj) &&
                    !@mkdir($target_dir . DIRECTORY_SEPARATOR . $obj)) {
                    @closedir($dh);
                    throw new \Exception("Error by copying to directory: " . $target_dir . DIRECTORY_SEPARATOR . $obj);
                }
                
                try {
                    $this->copyDirContents($source_dir . DIRECTORY_SEPARATOR . $obj, $target_dir . DIRECTORY_SEPARATOR . $obj);
                } catch (\Exception $ex) {
                    @closedir($dh);
                    throw $ex;
                }
            } else {
                if (!copy($source_dir . DIRECTORY_SEPARATOR . $obj, $target_dir . DIRECTORY_SEPARATOR . $obj)) {
                    throw new \Exception("Error by copying file: " . $target_dir . DIRECTORY_SEPARATOR . $obj);
                }
            }
        } // while
        
        @closedir($dh);
    } // copyDirContents
    
    protected function isEmptyGlobalNamespase(&$object_descriptor)
    {
        if ($object_descriptor["full_name"] != "\\") {
            return false;
        }
        
        $not_empty = false;
        foreach ($object_descriptor["children"] as &$children) {
            if (!empty($children)) {
                $not_empty = true;
                break;
            }
            
            unset($children);
        }
        
        return !$not_empty;
    } // isEmptyGlobalNamespase
    
    protected function removeNamespacePrefix($name)
    {
        return preg_replace("/.*\\\\/", "", $name);
    } // removeNamespacePrefix
    
    protected function escapeJs($text)
    {
        $text = str_replace("\\", "\\\\", $text);
        $text = str_replace("\n", "\\n", $text);
        $text = str_replace("\r", "\\r", $text);
        
        $text = str_replace("/", "\\/", $text);
        $text = str_replace("'", "\\'", $text);
        $text = str_replace("\"", "\\\"", $text);
        
        return $text;
    } // escapeJs
    
    protected function getNameByType($type)
    {
        switch ($type) {
            case "source_file":
                $type = "Source file";
                break;
            
            case "constant":
                $type = "Constant";
                break;
            
            case "global_variable":
                $type = "Global variable";
                break;
            
            case "class":
                $type = "Class";
                break;
            
            case "interface":
                $type = "Interface";
                break;
            
            case "trait":
                $type = "Trait";
                break;
            
            case "method":
                $type = "Method";
                break;
            
            case "property":
                $type = "Property";
                break;
            
            case "class_constant":
                $type = "Class constant";
                break;
        }
        
        return $type;
    } // getNameByType
    
    protected function objNameToFileName($name)
    {
        $name = trim($name, "/\\()");
        $name = str_replace("\\", ".", $name);
        $name = str_replace("/", ".", $name);
        
        return $name;
    } // objNameToFileName
    
    protected function getHelpFile(&$object_descriptor)
    {
        $file_name = $this->objNameToFileName($object_descriptor["full_name"]);
        $prefix = "";
        
        switch ($object_descriptor["type"]) {
            case "class_constant":
            case "method":
            case "property":
                return $this->getHelpFile($object_descriptor["parent"]);
            
            case "source_file":
                $prefix = "source-";
                break;
            
            case "constant":
                $prefix = "constant-";
                break;
            
            case "global_variable":
                $prefix = "global-";
                break;
            
            case "function":
                $prefix = "function-";
                break;
            
            case "package":
                $prefix = "package-";
                break;
            
            case "namespace":
                $prefix = "namespace-";
                
                if ($object_descriptor["full_name"] == "\\") {
                    $prefix = "";
                    $file_name = "global_namespace";
                }
                
                break;
            
            case "class":
                $prefix = "class-";
                break;
            
            case "interface":
                $prefix = "interface-";
                break;
            
            case "trait":
                $prefix = "trait-";
                break;
        }
        
        return $prefix . $file_name . ".html";
    } // getHelpFile
    
    protected function getAnchor(&$object_descriptor)
    {
        $anchor = "";
        switch ($object_descriptor["type"]) {
            case "class_constant":
            case "method":
            case "property":
                $anchor = "#" . trim($object_descriptor["short_name"], "/\\()");
                break;
        }
        
        return $anchor;
    } // getAnchor
    
    protected function getLabel(&$object_descriptor)
    {
        $label = $object_descriptor["name"];
        
        switch ($object_descriptor["type"]) {
            case "constant":
            case "class_constant":
                $label .= " - Constant";
                break;
            
            case "global_variable":
                $label .= " - Global variable";
                break;
            
            case "namespace":
                $label .= " - Namespace";
                break;
            
            case "package":
                $label .= " - Package";
                break;
            
            case "class":
                $label .= " - Class";
                break;
            
            case "interface":
                $label .= " - Interface";
                break;
            
            case "trait":
                $label .= " - Trait";
                break;
            
            case "class_property":
                $label .= " - Property";
                break;
        }
        
        return $label;
    } // getLabel
    
    protected function getTemplate($template_file)
    {
        $template_contents = file_get_contents($this->template_dir . $template_file);
        if ($template_contents === false) {
            throw new \Exception("File '" . $this->template_dir . $template_file . "' is not readable!");
        }
        
        return $template_contents;
    } // getTemplate
    
    protected function renderNamespaceList(&$object_list, &$contents, $deep = false)
    {
        $deepCollect = function (&$object_descriptor, &$namespace_array) {
            if (empty($object_descriptor["children"]["namespaces"])) {
                return;
            }
            
            foreach ($object_descriptor["children"]["namespaces"] as &$namespace_descriptor) {
                if ($this->isEmptyGlobalNamespase($namespace_descriptor)) {
                    continue;
                }
                
                $namespace_array[] = [
                    "namespace_name" => htmlspecialchars($namespace_descriptor["name"]),
                    "namespace_link" => $this->getHelpFile($namespace_descriptor)
                ];
            }
        };
        
        $namespace_array = [];
        foreach ($object_list as &$object_descriptor) {
            if ($this->isEmptyGlobalNamespase($object_descriptor)) {
                continue;
            }
            
            $namespace_array[] = [
                "namespace_name" => htmlspecialchars($object_descriptor["name"]),
                "namespace_link" => $this->getHelpFile($object_descriptor)
            ];
            
            if ($deep) {
                $deepCollect($object_descriptor, $namespace_array);
            }
        }
        
        explodeArray("namespaces", $namespace_array, $contents);
        
        checkIf("index_namespaces", $contents, function (&$body) use ($namespace_array) {
            if (empty($namespace_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderNamespaceList
    
    protected function renderPackageList(&$object_list, &$contents)
    {
        $package_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (empty($object_descriptor["name"])) {
                continue;
            }
            
            $package_array[] = [
                "package_name" => htmlspecialchars($object_descriptor["name"]),
                "package_link" => $this->getHelpFile($object_descriptor)
            ];
        }
        
        explodeArray("packages", $package_array, $contents);
        
        checkIf("index_packages", $contents, function (&$body) use ($package_array) {
            if (empty($package_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderPackageList
    
    protected function renderConstantList(&$object_list, &$contents)
    {
        $constant_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $row = [
                "constant_name" => htmlspecialchars($object_descriptor["name"]),
                "constant_link" => $this->getHelpFile($object_descriptor),
                "constant_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]))
            ];
            
            if (empty($object_descriptor["namespace"]["name"])) {
                $row["namespace"] = "";
                $row["namespace_link"] = "";
            } else {
                $row["namespace"] = htmlspecialchars($object_descriptor["namespace"]["name"]);
                $row["namespace_link"] = $this->getHelpFile($object_descriptor["namespace"]);
            }
            
            $constant_array[] = $row;
        }
        
        explodeArray("constants", $constant_array, $contents);
        
        checkIf("index_constants", $contents, function (&$body) use ($constant_array) {
            if (empty($constant_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderConstantList
    
    protected function renderGlobalVariableList(&$object_list, &$contents)
    {
        $glovar_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $glovar_array[] = [
                "global_name" => htmlspecialchars($object_descriptor["name"]),
                "global_link" => $this->getHelpFile($object_descriptor),
                "global_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]))
            ];
        }
        
        explodeArray("globals", $glovar_array, $contents);
        
        checkIf("index_globals", $contents, function (&$body) use ($glovar_array) {
            if (empty($glovar_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
        
        return;
        
        if (!preg_match("/<!-- if globals -->(.*?)<!-- endif globals -->/ism", $contents, $matches)) {
            return;
        }
        
        $object_list_sample = $matches[0];
        $object_list_body = $matches[1];
        
        if (count($object_list) < 1) {
            $contents = str_replace($object_list_sample, "", $contents);
            
            if (preg_match("/<!-- if index_globals -->(.*?)<!-- endif index_globals -->/ism", $contents, $matches)) {
                $contents = str_replace($matches[0], "", $contents);
            }
            
            return;
        }
        
        if (!preg_match("/<!-- foreach global -->(.*?)<!-- endforeach global -->/ism", $object_list_body, $matches)) {
            return;
        }
        
        $object_sample = $matches[0];
        
        $object_list_contents = "";
        
        foreach ($object_list as $name => &$object_descriptor) {
            if (empty($name)) {
                continue;
            }
            
            $object_item = $matches[1];
            
            $object_item = str_ireplace("{global_name}", "$" . htmlspecialchars($object_descriptor["name"]), $object_item);
            $object_item = str_ireplace("{global_link}", $this->getHelpFile($object_descriptor), $object_item);
            $object_item = str_ireplace("{global_description}", $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"])), $object_item);
            
            $object_list_contents .= $object_item;
        }
        
        $object_list_body = str_replace($object_sample, $object_list_contents, $object_list_body);
        
        $contents = str_replace($object_list_sample, $object_list_body, $contents);
    } // renderGlobalVariableList
    
    protected function renderFunctionList(&$object_list, &$contents)
    {
        $function_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $row = [
                "function_name" => htmlspecialchars($object_descriptor["name"]),
                "function_link" => $this->getHelpFile($object_descriptor),
                "function_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]))
            ];
            
            if (empty($object_descriptor["namespace"]["name"])) {
                $row["namespace"] = "";
                $row["namespace_link"] = "";
            } else {
                $row["namespace"] = htmlspecialchars($object_descriptor["namespace"]["name"]);
                $row["namespace_link"] = $this->getHelpFile($object_descriptor["namespace"]);
            }
            
            $function_array[] = $row;
        }
        
        explodeArray("functions", $function_array, $contents);
        
        checkIf("index_functions", $contents, function (&$body) use ($function_array) {
            if (empty($function_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderFuctionList
    
    protected function renderInterfaceList(&$object_list, &$contents)
    {
        $interface_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $row = [
                "interface_name" => htmlspecialchars($object_descriptor["name"]),
                "interface_link" => $this->getHelpFile($object_descriptor),
                "interface_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]))
            ];
            
            if (empty($object_descriptor["namespace"]["name"])) {
                $row["namespace"] = "";
                $row["namespace_link"] = "";
            } else {
                $row["namespace"] = htmlspecialchars($object_descriptor["namespace"]["name"]);
                $row["namespace_link"] = $this->getHelpFile($object_descriptor["namespace"]);
            }
            
            $interface_array[] = $row;
        }
        
        explodeArray("interfaces", $interface_array, $contents);
        
        checkIf("index_interfaces", $contents, function (&$body) use ($interface_array) {
            if (empty($interface_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderInterfaceList
    
    protected function renderClassList(&$object_list, &$contents)
    {
        $class_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $row = [
                "class_name" => htmlspecialchars($object_descriptor["name"]),
                "class_link" => $this->getHelpFile($object_descriptor),
                "class_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]))
            ];
            
            if (empty($object_descriptor["namespace"]["name"])) {
                $row["namespace"] = "";
                $row["namespace_link"] = "";
            } else {
                $row["namespace"] = htmlspecialchars($object_descriptor["namespace"]["name"]);
                $row["namespace_link"] = $this->getHelpFile($object_descriptor["namespace"]);
            }
            
            $class_array[] = $row;
        }
        
        explodeArray("classes", $class_array, $contents);
        
        checkIf("index_classes", $contents, function (&$body) use ($class_array) {
            if (empty($class_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderClassList
    
    protected function renderTraitList(&$object_list, &$contents)
    {
        $trait_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $row = [
                "trait_name" => htmlspecialchars($object_descriptor["name"]),
                "trait_link" => $this->getHelpFile($object_descriptor),
                "trait_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]))
            ];
            
            if (empty($object_descriptor["namespace"]["name"])) {
                $row["namespace"] = "";
                $row["namespace_link"] = "";
            } else {
                $row["namespace"] = htmlspecialchars($object_descriptor["namespace"]["name"]);
                $row["namespace_link"] = $this->getHelpFile($object_descriptor["namespace"]);
            }
            
            $trait_array[] = $row;
        }
        
        explodeArray("traits", $trait_array, $contents);
        
        checkIf("index_traits", $contents, function (&$body) use ($trait_array) {
            if (empty($trait_array)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
    } // renderTraitList
    
    protected function renderBreadcrumbs(&$layout_contents, $current_object = "")
    {
        $breadcrumbs_contents = $this->getTemplate("breadcrumbs.tpl");
        
        do {
            $types = [
                "namespace" => ["caption" => "Namespace", "class" => "label-danger"],
                "interface" => ["caption" => "Interface", "class" => "label-info"],
                "class" => ["caption" => "Class", "class" => "label-success"],
                "function" => ["caption" => "Function", "class" => "label-primary"],
                "constant" => ["caption" => "Constant", "class" => "label-warning"],
                "global_variable" => ["caption" => "Global variable", "class" => "label-danger"],
                "package" => ["caption" => "Package", "class" => "label-success"],
                "trait" => ["caption" => "Trait", "class" => "label-danger"]
            ];
            
            $object_type = "";
            if (!empty($this->dictionary["lookup"][$current_object]["type"])) {
                $object_type = $this->dictionary["lookup"][$current_object]["type"];
            }
            
            if ($current_object == "\\") {
                $object_type = "namespace";
            }
            
            if (preg_match("/<!-- if label -->(.*?)<!-- endif label -->/ism", $breadcrumbs_contents, $matches)) {
                $label_sample = $matches[0];
                $label_contents = $matches[1];
                
                if (empty($object_type) || empty($types[$object_type])) {
                    $breadcrumbs_contents = str_ireplace($label_sample, "", $breadcrumbs_contents);
                } else {
                    $label_contents = str_ireplace("{object_type}", $types[$object_type]["caption"], $label_contents);
                    $label_contents = str_ireplace("{object_class}", $types[$object_type]["class"], $label_contents);
                    
                    $breadcrumbs_contents = str_ireplace($label_sample, $label_contents, $breadcrumbs_contents);
                }
            } // if label
            
            $object_short_name = $this->removeNamespacePrefix($current_object);
            if (empty($object_short_name) && $object_type == "namespace") {
                $object_short_name = "\\";
            }
            
            if (preg_match("/<!-- foreach namespace_part -->(.*?)<!-- endforeach namespace_part -->/ism", $breadcrumbs_contents, $matches)) {
                $namespace_parts_sample = $matches[0];
                
                $namespace_parts_content = "";
                
                $namespace_parts = preg_split("/\\\\/", $current_object, 0, PREG_SPLIT_NO_EMPTY);
                
                // we need not repat the final object name in the namespace path
                array_pop($namespace_parts);
                
                $current_namespace = "";
                
                foreach ($namespace_parts as $namespace_part) {
                    $namespace_part_item = $matches[1];
                    
                    $current_namespace .= "\\" . $namespace_part;
                    
                    $namespace_link = $this->getHelpFile($this->dictionary["namespaces"][$current_namespace]);
                    
                    $namespace_part_item = str_ireplace("{namespace_part}", htmlspecialchars($namespace_part), $namespace_part_item);
                    $namespace_part_item = str_ireplace("{namespace_link}", $namespace_link, $namespace_part_item);
                    
                    $namespace_parts_content .= $namespace_part_item;
                }
                
                $breadcrumbs_contents = str_ireplace($namespace_parts_sample, $namespace_parts_content, $breadcrumbs_contents);
            }
            
            $breadcrumbs_contents = str_ireplace("{object_name}", $object_short_name, $breadcrumbs_contents);
        } while (false);
        
        $layout_contents = str_ireplace("{breadcrumbs}", $breadcrumbs_contents, $layout_contents);
    } // renderBreadcrumbs
    
    protected function renderMenu(&$layout_contents, $file_name)
    {
        $menu_contents = $this->getTemplate("menu.tpl");
        
        if (preg_match("/<!-- foreach menuitem -->(.*?)<!-- endforeach menuitem -->/ism", $menu_contents, $matches)) {
            $contents = "";
            
            $item = $matches[1];
            $item = str_ireplace("{item_target}", "index.html", $item);
            $item = str_ireplace("{item_title}", "Overview", $item);
            $item = str_ireplace("{item_active}", $file_name == "index.html" ? "active" : "", $item);
            $contents .= $item;
            
            if ($this->countNotIgnored($this->dictionary["classes"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "classes.html", $item);
                $item = str_ireplace("{item_title}", "Classes", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "class") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if ($this->countNotIgnored($this->dictionary["interfaces"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "interfaces.html", $item);
                $item = str_ireplace("{item_title}", "Interfaces", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "interface") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if ($this->countNotIgnored($this->dictionary["traits"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "traits.html", $item);
                $item = str_ireplace("{item_title}", "Traits", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "trait") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if ($this->countNotIgnored($this->dictionary["functions"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "functions.html", $item);
                $item = str_ireplace("{item_title}", "Functions", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "function") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if ($this->countNotIgnored($this->dictionary["constants"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "constants.html", $item);
                $item = str_ireplace("{item_title}", "Constants", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "constant") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if ($this->countNotIgnored($this->dictionary["global_variables"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "globals.html", $item);
                $item = str_ireplace("{item_title}", "Globals", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "global") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if ($this->countNotIgnored($this->dictionary["todos"]) > 0 ||
                $this->countNotIgnored($this->dictionary["since"]) > 0 ||
                $this->countNotIgnored($this->dictionary["deprecated"]) > 0) {
                $item = $matches[1];
                $item = str_ireplace("{item_target}", "log.html", $item);
                $item = str_ireplace("{item_title}", "Log", $item);
                $item = str_ireplace("{item_active}", strpos($file_name, "log") === 0 ? "active" : "", $item);
                $contents .= $item;
            }
            
            if (!empty($this->config["user_files"])) {
                foreach ($this->config["user_files"] as &$user_file) {
                    $item = $matches[1];
                    $item = str_ireplace("{item_target}", basename($user_file["path"]), $item);
                    $item = str_ireplace("{item_title}", basename($user_file["menu_title"]), $item);
                    $item = str_ireplace("{item_active}", strpos($file_name, basename($user_file["path"])) === 0 ? "active" : "", $item);
                    $contents .= $item;
                }
            }
            
            $menu_contents = str_replace($matches[0], $contents, $menu_contents);
        }
        
        $layout_contents = str_ireplace("{menu}", $menu_contents, $layout_contents);
    } // renderMenu
    
    protected function createFile($file_name, $contents, &$replacements, $current_object = "")
    {
        $layout_contents = $this->getTemplate("layout.tpl");
        
        $layout_contents = str_ireplace("{contents}", $contents, $layout_contents);
        
        $this->renderMenu($layout_contents, $file_name);
        
        $this->renderBreadcrumbs($layout_contents, $current_object);
        
        $replacements["{gen_date}"] = $this->gen_date;
        
        foreach ($replacements as $pattern => $replacement) {
            $layout_contents = str_ireplace($pattern, $replacement, $layout_contents);
        }
        
        if (!file_put_contents($this->target_dir . $file_name, $layout_contents)) {
            throw new \Exception("Error by writing the file: " . $this->target_dir . $file_name . "!");
        }
    } // createFile
    
    protected function generateLookupJsFile()
    {
        echo_standard("\nCreating JS lookup file");
        
        $file_name = "elementlist.js";
        $contents = "var ApiGen = ApiGen || {};" . "\n";
        $contents .= "ApiGen.elements = [";
        
        if (!empty($this->dictionary["lookup"])) {
            foreach ($this->dictionary["lookup"] as $key => &$object_descriptor) {
                if (!empty($object_descriptor["ignore"])) {
                    continue;
                }
                
                if (!empty($object_descriptor["parent"]) && !empty($object_descriptor["parent"]["ignore"])) {
                    continue;
                }
                
                $file = $this->getHelpFile($object_descriptor);
                $anchor = $this->getAnchor($object_descriptor);
                $label = $this->escapeJs($this->getLabel($object_descriptor));
                
                $contents .= "\n" . '{"file":"' . $file . $anchor . '","label":"' . $label . '"},';
            }
        }
        
        $contents = rtrim($contents, ",");
        
        $contents .= "\n" . "];" . "\n";
        
        if (!file_put_contents($this->target_dir . $file_name, $contents)) {
            throw new \Exception("Error by writing file: " . $this->target_dir . $file_name);
        }
    } // generateLookupJsFile
    
    protected function generateUserFiles()
    {
        echo_standard("\nCreating user defined files");
        
        if (empty($this->config["user_files"])) {
            return;
        }
        
        foreach ($this->config["user_files"] as &$user_file) {
            $replacements = [];
            $replacements["{title}"] = $user_file["title"] . " - " . htmlspecialchars($this->config["title"]);
            $replacements["{header_title}"] = $user_file["title"];
            
            $contents = $this->getTemplate("user_content.tpl");
            
            $body = file_get_contents($user_file["path"]);
            if ($body === false) {
                throw new \Exception("File '" . $user_file["path"] . "' is not readable!");
            }
            
            $contents = str_ireplace("{contents}", $body, $contents);
            
            $this->createFile(basename($user_file["path"]), $contents, $replacements);
        }
    } // generateUserFiles
    
    protected function generateSourceFiles()
    {
        echo_standard("\nCreating source files");
        
        $highlighter = new \FSHL\Highlighter(new \FSHL\Output\Html());
        $highlighter->setLexer(new \FSHL\Lexer\Php());
        $highlighter->setOptions(\FSHL\Highlighter::OPTION_TAB_INDENT | \FSHL\Highlighter::OPTION_LINE_COUNTER);
        
        $source_code_template = $this->getTemplate("source_code.tpl");
        
        foreach ($this->dictionary["source_files"] as &$object_descriptor) {
            if (!file_exists($this->source_dir . $object_descriptor["relative_path"])) {
                throw new \Exception("The source file '" . $this->source_dir . $object_descriptor["relative_path"] . "' does not exist!");
            }
            
            $contents = trim(file_get_contents($this->source_dir . $object_descriptor["relative_path"]));
            $contents = $highlighter->highlight($contents);
            
            $contents = preg_replace("/<span class=\"line\">(\s*)(\\d+):/", "<span class=\"line\" id=\"$2\">$1$2:", $contents);
            
            $contents = str_ireplace("{contents}", $contents, $source_code_template);
            
            $replacements = [];
            $replacements["{title}"] = "Source Code: " . $object_descriptor["full_name"] . " - " . htmlspecialchars($this->config["title"]);
            $replacements["{header_title}"] = "Source Code: " . $object_descriptor["full_name"];
            
            $target_file = $this->getHelpFile($object_descriptor);
            $this->createFile($target_file, $contents, $replacements);
        }
    } // generateSourceFiles
    
    protected function generateNamespaceFiles()
    {
        echo_standard("\nCreating namespace files");
        
        foreach ($this->dictionary["namespaces"] as &$object_descriptor) {
            if ($this->isEmptyGlobalNamespase($object_descriptor)) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Namespace: " . $object_descriptor["name"];
            $replacements["{header_title}"] = "Namespace: " . $object_descriptor["name"];
            
            $namespace_contents = $this->getTemplate("namespace.tpl");
            
            $this->renderNamespaceList($object_descriptor["children"]["namespaces"], $namespace_contents, true);
            
            $this->renderConstantList($object_descriptor["children"]["constants"], $namespace_contents);
            
            $this->renderFunctionList($object_descriptor["children"]["functions"], $namespace_contents);
            
            $this->renderInterfaceList($object_descriptor["children"]["interfaces"], $namespace_contents);
            
            $this->renderClassList($object_descriptor["children"]["classes"], $namespace_contents);
            
            $this->renderTraitList($object_descriptor["children"]["traits"], $namespace_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $namespace_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateNamespaceFiles
    
    protected function generatePackageFiles()
    {
        echo_standard("\nCreating package files");
        
        foreach ($this->dictionary["packages"] as &$object_descriptor) {
            if (empty($object_descriptor["name"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Package: " . $object_descriptor["name"];
            $replacements["{header_title}"] = "Package: " . $object_descriptor["name"];
            
            $package_contents = $this->getTemplate("package.tpl");
            
            $this->renderConstantList($object_descriptor["children"]["constants"], $package_contents);
            
            $this->renderGlobalVariableList($object_descriptor["children"]["global_variables"], $package_contents);
            
            $this->renderFunctionList($object_descriptor["children"]["functions"], $package_contents);
            
            $this->renderInterfaceList($object_descriptor["children"]["interfaces"], $package_contents);
            
            $this->renderClassList($object_descriptor["children"]["classes"], $package_contents);
            
            $this->renderTraitList($object_descriptor["children"]["traits"], $package_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $package_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generatePackageFiles
    
    protected function generateClassFiles()
    {
        echo_standard("\nCreating class files");
        
        if ($this->countNotIgnored($this->dictionary["classes"]) == 0) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Classes - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Classes - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("classes.tpl");
        
        $this->renderClassList($this->dictionary["classes"], $contents);
        
        $this->createFile("classes.html", $contents, $replacements);
        
        foreach ($this->dictionary["classes"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Class: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Class: " . htmlspecialchars($object_descriptor["name"]);
            
            $constant_contents = $this->getTemplate("class.tpl");
            
            $this->generateTagsTexts($object_descriptor, $constant_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateClassFiles
    
    protected function generateInterfaceFiles()
    {
        echo_standard("\nCreating interface files");
        
        if ($this->countNotIgnored($this->dictionary["interfaces"]) == 0) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Interfaces - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Interfaces - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("interfaces.tpl");
        
        $this->renderInterfaceList($this->dictionary["interfaces"], $contents);
        
        $this->createFile("interfaces.html", $contents, $replacements);
        
        foreach ($this->dictionary["interfaces"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Interface: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Interface: " . htmlspecialchars($object_descriptor["name"]);
            
            $constant_contents = $this->getTemplate("interface.tpl");
            
            $this->generateTagsTexts($object_descriptor, $constant_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateInterfaceFiles
    
    protected function generateTraitFiles()
    {
        echo_standard("\nCreating trait files");
        
        if ($this->countNotIgnored($this->dictionary["traits"]) == 0) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Traits - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Traits - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("traits.tpl");
        
        $this->renderTraitList($this->dictionary["traits"], $contents);
        
        $this->createFile("traits.html", $contents, $replacements);
        
        foreach ($this->dictionary["traits"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Trait: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Trait: " . htmlspecialchars($object_descriptor["name"]);
            
            $constant_contents = $this->getTemplate("trait.tpl");
            
            $this->generateTagsTexts($object_descriptor, $constant_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateTraitFiles
    
    protected function generateFunctionFiles()
    {
        echo_standard("\nCreating function files");
        
        if ($this->countNotIgnored($this->dictionary["functions"]) == 0) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Functions - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Functions - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("functions.tpl");
        
        $this->renderFunctionList($this->dictionary["functions"], $contents);
        
        $this->createFile("functions.html", $contents, $replacements);
        
        foreach ($this->dictionary["functions"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Function: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Function: " . htmlspecialchars($object_descriptor["name"]);
            
            $constant_contents = $this->getTemplate("function.tpl");
            
            $this->generateTagsTexts($object_descriptor, $constant_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateFunctionFiles
    
    protected function generateConstantFiles()
    {
        if ($this->countNotIgnored($this->dictionary["constants"]) == 0) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Constants - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Constants - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("constants.tpl");
        
        $this->renderConstantList($this->dictionary["constants"], $contents);
        
        $this->createFile("constants.html", $contents, $replacements);
        
        foreach ($this->dictionary["constants"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Constant: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Constant: " . htmlspecialchars($object_descriptor["name"]);
            
            $constant_contents = $this->getTemplate("constant.tpl");
            
            $this->generateTagsTexts($object_descriptor, $constant_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateConstantFiles
    
    protected function generateLogFile()
    {
        echo_standard("\nCreating log file");
        
        if ($this->countNotIgnored($this->dictionary["todos"]) == 0 &&
            $this->countNotIgnored($this->dictionary["since"]) == 0 &&
            $this->countNotIgnored($this->dictionary["deprecated"]) == 0
        ) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Activity log - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Activity log - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("log.tpl");
        
        $object_list = [];
        foreach ($this->dictionary["since"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            if (empty($object_descriptor["name"])) {
                continue;
            }
            
            $read_name = $this->getNameByType($object_descriptor["type"]);
            $object_row = [
                "object_title" => htmlspecialchars($read_name . ": " . $object_descriptor["name"]),
                "object_full_name" => $object_descriptor["full_name"],
                "object_link" => $this->getHelpFile($object_descriptor) . $this->getAnchor($object_descriptor)
            ];
            
            $object_row["object_since"] = [];
            if (!empty($object_descriptor["since"])) {
                foreach ($object_descriptor["since"] as $since) {
                    $object_row["object_since"][] = [
                        "object_version" => htmlspecialchars($since["version"]),
                        "object_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($since["description"]))
                    ];
                }
            }
            
            $object_list[] = $object_row;
        }
        explodeArray("change_log", $object_list, $contents);
        
        checkIf("index_change_log", $contents, function (&$body) use ($object_list) {
            if (empty($object_list)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
        
        $object_list = [];
        foreach ($this->dictionary["deprecated"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            if (empty($object_descriptor["name"])) {
                continue;
            }
            
            $read_name = $this->getNameByType($object_descriptor["type"]);
            $object_row = [
                "object_title" => htmlspecialchars($read_name . ": " . $object_descriptor["name"]),
                "object_full_name" => $object_descriptor["full_name"],
                "object_link" => $this->getHelpFile($object_descriptor) . $this->getAnchor($object_descriptor)
            ];
            
            $object_row["object_deprecated"] = [];
            if (!empty($object_descriptor["deprecated_version"])) {
                $object_row["object_deprecated"][] = [
                    "object_version" => htmlspecialchars($object_descriptor["deprecated_version"]),
                    "object_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["deprecated_description"]))
                ];
            }
            
            $object_list[] = $object_row;
        }
        explodeArray("deprecated", $object_list, $contents);
        
        checkIf("index_deprecated", $contents, function (&$body) use ($object_list) {
            if (empty($object_list)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
        
        $object_list = [];
        foreach ($this->dictionary["todos"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            if (empty($object_descriptor["name"])) {
                continue;
            }
            
            $read_name = $this->getNameByType($object_descriptor["type"]);
            $object_row = [
                "object_title" => htmlspecialchars($read_name . ": " . $object_descriptor["name"]),
                "object_full_name" => $object_descriptor["full_name"],
                "object_link" => $this->getHelpFile($object_descriptor) . $this->getAnchor($object_descriptor)
            ];
            
            $object_row["object_todos"] = [];
            if (!empty($object_descriptor["todos"])) {
                foreach ($object_descriptor["todos"] as $todo) {
                    $object_row["object_todos"][] = [
                        "object_description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($todo["description"]))
                    ];
                }
            }
            
            $object_list[] = $object_row;
        }
        explodeArray("todos", $object_list, $contents);
        
        checkIf("index_todos", $contents, function (&$body) use ($object_list) {
            if (empty($object_list)) {
                $body = "";
            } else {
                // let body as is.
            }
        });
        
        $this->createFile("log.html", $contents, $replacements);
    } // generateLogFile
    
    protected function generateGlobalVariableFiles()
    {
        echo_standard("\nCreating global variable files");
        
        if ($this->countNotIgnored($this->dictionary["global_variables"]) == 0) {
            return;
        }
        
        $replacements = [];
        $replacements["{title}"] = "Global variables - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Global variables - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("globals.tpl");
        
        $this->renderGlobalVariableList($this->dictionary["global_variables"], $contents);
        
        $this->createFile("globals.html", $contents, $replacements);
        
        foreach ($this->dictionary["global_variables"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }
            
            $replacements = [];
            $replacements["{title}"] = "Global variable: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Global variable: " . htmlspecialchars($object_descriptor["name"]);
            
            $global_contents = $this->getTemplate("global.tpl");
            
            $this->generateTagsTexts($object_descriptor, $global_contents);
            
            $this->createFile($this->getHelpFile($object_descriptor), $global_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateGlobalVariableFiles
    
    protected function generateIndexFile()
    {
        echo_standard("\nCreating index file");
        
        $replacements = [];
        $replacements["{title}"] = "Overview - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Overview - " . htmlspecialchars($this->config["title"]);
        
        $contents = $this->getTemplate("index.tpl");
        
        $this->renderNamespaceList($this->dictionary["namespaces"], $contents);
        
        $this->renderPackageList($this->dictionary["packages"], $contents);
        
        $this->renderConstantList($this->dictionary["constants"], $contents);
        
        $this->renderGlobalVariableList($this->dictionary["global_variables"], $contents);
        
        $this->renderFunctionList($this->dictionary["functions"], $contents);
        
        $this->renderInterfaceList($this->dictionary["interfaces"], $contents);
        
        $this->renderClassList($this->dictionary["classes"], $contents);
        
        $this->renderTraitList($this->dictionary["traits"], $contents);
        
        $this->createFile("index.html", $contents, $replacements);
    } // generateIndexFile
    
    protected function linkIfExists($type_name)
    {
        $types = explode("|", $type_name);
        
        foreach ($types as &$type) {
            if (empty($this->dictionary["lookup"][$type])) {
                $type = htmlspecialchars($type);
            } else {
                $href = $this->getHelpFile($this->dictionary["lookup"][$type]) . $this->getAnchor($this->dictionary["lookup"][$type]);
                $type = "<a href='$href'>" . htmlspecialchars($type) . "</a>";
            }
        }
        
        return implode("|", $types);
    } // linkIfExists
    
    protected function generateTagsTexts(&$object_descriptor, &$contents)
    {
        $object_row = [];
        $object_row["definition"] = $this->formatPhpStatement($object_descriptor, checkempty($object_descriptor["php_statement"]));
        
        $object_row["description"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]));
        $object_row["long_description"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["description"]));
        
        $object_row["package"] = htmlspecialchars($object_descriptor["package"]["name"]);
        if (!empty($object_row["package"])) {
            $href = $this->getHelpFile($object_descriptor["package"]);
            $object_row["package"] = "<a href='$href'>" . $object_row["package"] . "</a>";
        }
        
        $object_row["source-file"] = htmlspecialchars($object_descriptor["source_file"]["relative_path"]);
        if (!empty($object_row["source-file"])) {
            $href = $this->getHelpFile($object_descriptor["source_file"]) . "#" . $object_descriptor["start_line"];
            $object_row["source-file"] = "<a href='$href'>" . $object_row["source-file"] . "</a>";
        }
        
        if (!empty($object_descriptor["val_type"])) {
            $object_row["type"] = $this->linkIfExists($object_descriptor["val_type"]);
        } else {
            $object_row["type"] = "";
        }
        
        if (!empty($object_descriptor["return_type"])) {
            $object_row["return_type"] = $this->linkIfExists($object_descriptor["return_type"]);
        } else {
            $object_row["return_type"] = "";
        }
        $object_row["return_description"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["return_description"]));
        
        if (!empty($object_descriptor["copyright"])) {
            $object_row["copyright"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["copyright"]));
        } else {
            $object_row["copyright"] = "";
        }
        
        if (!empty($object_descriptor["license"])) {
            $object_row["license"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["license"]));
        } else {
            $object_row["license"] = "";
        }
        
        if (!empty($object_descriptor["deprecated_version"])) {
            $object_row["deprecated_version"] = $object_descriptor["deprecated_version"];
        } else {
            $object_row["deprecated_version"] = "";
        }
        $object_row["deprecated_description"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["deprecated_description"]));
        
        if (!empty($object_descriptor["version"])) {
            $object_row["version"] = $object_descriptor["version"];
        } else {
            $object_row["version"] = "";
        }
        $object_row["version_description"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["version_description"]));
        
        if (isset($object_descriptor["internal"])) {
            if (empty($object_descriptor["internal"])) {
                $object_row["internal"] = "Internal use only!";
            } else {
                $object_row["internal"] = $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["internal"]));
            }
        } else {
            $object_row["internal"] = "";
        }
        
        explodeRow($object_row, $contents);
        
        $labels = [];
        if (!empty($object_descriptor["flags"]) && $object_descriptor["flags"] & 16) {
            $labels[] = [
                "label_text" => "abstract",
                "label_class" => "label-primary"
            ];
        }
        
        if (!empty($object_descriptor["flags"]) && $object_descriptor["flags"] & 32) {
            $labels[] = [
                "label_text" => "final",
                "label_class" => "label-danger"
            ];
        }
        explodeArray("class_labels", $labels, $contents);
        
        $params = [];
        if (!empty($object_descriptor["params"])) {
            foreach ($object_descriptor["params"] as $param) {
                $pass_type = "by value";
                if (!empty($param["by_ref"])) {
                    $pass_type = "by reference";
                }
                if (!empty($param["variadic"])) {
                    $pass_type .= ", variadic";
                }
                
                $params[] = [
                    "param_name" => htmlspecialchars($param["name"]),
                    "pass_type" => htmlspecialchars($pass_type),
                    "param_type" => $this->linkIfExists($param["val_type"]),
                    "param_default" => htmlspecialchars(checkempty($param["default_value"])),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($param["description"]))
                ];
            }
        }
        explodeArray("parameters", $params, $contents);
        
        $authors = [];
        if (!empty($object_descriptor["authors"])) {
            foreach ($object_descriptor["authors"] as $author_data) {
                $author = htmlspecialchars($author_data["name"]);
                if (!empty($author_data["email"])) {
                    $author .= " &lt;<a href='mailto:$author_data[email]'>" . htmlspecialchars($author_data["email"]) . "</a>&gt;";
                }
                
                $authors[] = ["author" => $author];
            }
        }
        explodeArray("authors", $authors, $contents);
        
        $extends = [];
        if (!empty($object_descriptor["extends"])) {
            foreach ($object_descriptor["extends"] as $extend) {
                $extends[] = [
                    "item" => $this->linkIfExists($extend)
                ];
            }
        }
        explodeArray("extends", $extends, $contents);
        
        $implements = [];
        if (!empty($object_descriptor["implements"])) {
            foreach ($object_descriptor["implements"] as $implement) {
                $implements[] = [
                    "item" => $this->linkIfExists($implement)
                ];
            }
        }
        explodeArray("implements", $implements, $contents);
        
        $uses_traits = [];
        if (!empty($object_descriptor["uses_traits"])) {
            foreach ($object_descriptor["uses_traits"] as $uses_trait) {
                $uses_traits[] = [
                    "item" => $this->linkIfExists($uses_trait)
                ];
            }
        }
        explodeArray("uses_traits", $uses_traits, $contents);
        
        $known_inheritances = [];
        if (!empty($object_descriptor["known_inheritances"])) {
            foreach ($object_descriptor["known_inheritances"] as $known_inheritance) {
                $known_inheritances[] = [
                    "item" => $this->linkIfExists($known_inheritance)
                ];
            }
        }
        explodeArray("known_inheritances", $known_inheritances, $contents);
        
        $known_implementations = [];
        if (!empty($object_descriptor["known_implementations"])) {
            foreach ($object_descriptor["known_implementations"] as $known_implementation) {
                $known_implementations[] = [
                    "item" => $this->linkIfExists($known_implementation)
                ];
            }
        }
        explodeArray("known_implementations", $known_implementations, $contents);
        
        $known_usages = [];
        if (!empty($object_descriptor["known_usages"])) {
            foreach ($object_descriptor["known_usages"] as $known_usage) {
                $known_usages[] = [
                    "item" => $this->linkIfExists($known_usage)
                ];
            }
        }
        explodeArray("known_usages", $known_usages, $contents);
        
        $see = [];
        if (!empty($object_descriptor["see"])) {
            foreach ($object_descriptor["see"] as $see_data) {
                $see[] = [
                    "item" => $this->linkIfExists($see_data["reference"]),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($see_data["description"]))
                ];
            }
        }
        explodeArray("see", $see, $contents);
        
        $uses = [];
        if (!empty($object_descriptor["uses"])) {
            foreach ($object_descriptor["uses"] as $uses_data) {
                $uses[] = [
                    "item" => $this->linkIfExists($uses_data["reference"]),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($uses_data["description"]))
                ];
            }
        }
        explodeArray("uses", $uses, $contents);
        
        $used_by = [];
        if (!empty($object_descriptor["used-by"])) {
            foreach ($object_descriptor["used-by"] as $used_by_data) {
                $used_by[] = [
                    "item" => $this->linkIfExists($used_by_data["reference"]),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($used_by_data["description"]))
                ];
            }
        }
        explodeArray("used_by", $used_by, $contents);
        
        $sinces = [];
        if (!empty($object_descriptor["since"])) {
            foreach ($object_descriptor["since"] as $since) {
                $sinces[] = [
                    "version" => htmlspecialchars($since["version"]),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($since["description"]))
                ];
            }
        }
        explodeArray("since", $sinces, $contents);
        
        $todos = [];
        if (!empty($object_descriptor["todos"])) {
            foreach ($object_descriptor["todos"] as $todo) {
                $todos[] = [
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($todo["description"]))
                ];
            }
        }
        explodeArray("todos", $todos, $contents);
        
        $links = [];
        if (!empty($object_descriptor["links"])) {
            foreach ($object_descriptor["links"] as $link) {
                $links[] = [
                    "url" => htmlspecialchars($link["url"]),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($link["description"]))
                ];
            }
        }
        explodeArray("links", $links, $contents);
        
        $throws = [];
        if (!empty($object_descriptor["throws"])) {
            foreach ($object_descriptor["throws"] as $throw) {
                $throws[] = [
                    "item" => $this->linkIfExists($throw["type"]),
                    "description" => $this->formatText($object_descriptor["source_file"]["uses_map"], checkempty($throw["description"]))
                ];
            }
        }
        explodeArray("throws", $throws, $contents);
        
        $iterations = [
            ["type" => "constants", "name" => "Constant"],
            ["type" => "properties", "name" => "Property"],
            ["type" => "methods", "name" => "Method"]
        ];
        
        foreach ($iterations as $iteration) {
            $objects = [];
            if (!empty($object_descriptor[$iteration["type"]])) {
                foreach ($object_descriptor[$iteration["type"]] as $object) {
                    $object_visibility = "<span class='label label-success'>public</span>";
                    if (!empty($object["flags"])) {
                        if ($object["flags"] & 2) {
                            $object_visibility = "<span class='label label-warning'>protected</span>";
                        } elseif ($object["flags"] & 4) {
                            $object_visibility = "<span class='label label-danger'>private</span>";
                        } else {
                        }
                    }
                    
                    $object_row = [
                        "object_anchor" => trim($this->getAnchor($object), "#"),
                        "object_name" => $object["short_name"],
                        "object_title" => $iteration["name"] . ": " . $object["name"],
                        "object_link" => $this->getAnchor($object),
                        "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($object["summary"])),
                        "object_long_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($object["description"])),
                        "object_definition" => $this->formatPhpStatement($object, checkempty($object["php_statement"])),
                        "object_visibility" => $object_visibility
                    ];
                    
                    $object_row["source-file"] = htmlspecialchars($object["source_file"]["relative_path"]);
                    if (!empty($object_row["source-file"])) {
                        $href = $this->getHelpFile($object["source_file"]) . "#" . $object["start_line"];
                        $object_row["object_source-file"] = "<a href='$href'>" . $object_row["source-file"] . "</a>";
                    }
                    
                    if (!empty($object["val_type"])) {
                        $object_row["object_type"] = $this->linkIfExists($object["val_type"]);
                    } else {
                        $object_row["object_type"] = "";
                    }
                    
                    if (!empty($object["return_type"])) {
                        $object_row["object_return_type"] = $this->linkIfExists($object["return_type"]);
                    } else {
                        $object_row["object_return_type"] = "";
                    }
                    $object_row["object_return_description"] = $this->formatText($object["source_file"]["uses_map"], checkempty($object["return_description"]));
                    
                    if (!empty($object["overrides"])) {
                        $object_row["object_overrides"] = $this->linkIfExists($object["overrides"]);
                    } else {
                        $object_row["object_overrides"] = "";
                    }
                    
                    if (!empty($object["copyright"])) {
                        $object_row["object_copyright"] = htmlspecialchars($object["copyright"]);
                    } else {
                        $object_row["object_copyright"] = "";
                    }
                    
                    if (!empty($object["license"])) {
                        $object_row["object_license"] = $object["license"];
                    } else {
                        $object_row["object_license"] = "";
                    }
                    
                    if (!empty($object["deprecated_version"])) {
                        $object_row["object_deprecated_version"] = $object["deprecated_version"];
                    } else {
                        $object_row["object_deprecated_version"] = "";
                    }
                    $object_row["object_deprecated_description"] = $this->formatText($object["source_file"]["uses_map"], checkempty($object["deprecated_description"]));
                    
                    if (!empty($object["version"])) {
                        $object_row["object_version"] = $object["version"];
                    } else {
                        $object_row["object_version"] = "";
                    }
                    $object_row["object_version_description"] = $this->formatText($object["source_file"]["uses_map"], checkempty($object["version_description"]));
                    
                    if (isset($object["internal"])) {
                        if (empty($object["internal"])) {
                            $object_row["object_internal"] = "Internal use only!";
                        } else {
                            $object_row["object_internal"] = $object["internal"];
                        }
                    } else {
                        $object_row["object_internal"] = "";
                    }
                    
                    $object_row["object_parameters"] = [];
                    if (!empty($object["params"])) {
                        foreach ($object["params"] as $param) {
                            $pass_type = "by value";
                            if (!empty($param["by_ref"])) {
                                $pass_type = "by reference";
                            }
                            if (!empty($param["variadic"])) {
                                $pass_type .= ", variadic";
                            }
                            
                            $object_row["object_parameters"][] = [
                                "object_param_name" => htmlspecialchars($param["name"]),
                                "object_pass_type" => htmlspecialchars($pass_type),
                                "object_param_type" => $this->linkIfExists($param["val_type"]),
                                "object_param_default" => htmlspecialchars(checkempty($param["default_value"])),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($param["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_labels"] = [];
                    if (!empty($object["flags"]) && $object["flags"] & 16) {
                        $object_row["object_labels"][] = [
                            "object_label_text" => "abstract",
                            "object_label_class" => "label-info"
                        ];
                    }
                    
                    if (!empty($object["flags"]) && $object["flags"] & 32) {
                        $object_row["object_labels"][] = [
                            "object_label_text" => "final",
                            "object_label_class" => "label-special"
                        ];
                    }
                    
                    if (!empty($object["flags"]) && $object["flags"] & 8) {
                        $object_row["object_labels"][] = [
                            "object_label_text" => "static",
                            "object_label_class" => "label-primary"
                        ];
                    }
                    
                    $object_row["object_authors"] = [];
                    if (!empty($object["authors"])) {
                        foreach ($object["authors"] as $author_data) {
                            $author = htmlspecialchars($author_data["name"]);
                            if (!empty($author_data["email"])) {
                                $author .= " &lt;<a href='mailto:$author_data[email]'>" . htmlspecialchars($author_data["email"]) . "</a>&gt;";
                            }
                            
                            $object_row["object_authors"][] = ["object_author" => $author];
                        }
                    }
                    
                    $object_row["object_see"] = [];
                    if (!empty($object["see"])) {
                        foreach ($object["see"] as $see_data) {
                            $object_row["object_see"][] = [
                                "object_item" => $this->linkIfExists($see_data["reference"]),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($see_data["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_uses"] = [];
                    if (!empty($object["uses"])) {
                        foreach ($object["uses"] as $uses_data) {
                            $object_row["object_uses"][] = [
                                "object_item" => $this->linkIfExists($uses_data["reference"]),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($uses_data["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_used_by"] = [];
                    if (!empty($object["used-by"])) {
                        foreach ($object["used-by"] as $used_by_data) {
                            $object_row["object_used_by"][] = [
                                "object_item" => $this->linkIfExists($used_by_data["reference"]),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($used_by_data["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_since"] = [];
                    if (!empty($object["since"])) {
                        foreach ($object["since"] as $since) {
                            $object_row["object_since"][] = [
                                "object_version" => htmlspecialchars($since["version"]),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($since["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_todos"] = [];
                    if (!empty($object["todos"])) {
                        foreach ($object["todos"] as $todo) {
                            $object_row["object_todos"][] = [
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($todo["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_links"] = [];
                    if (!empty($object["links"])) {
                        foreach ($object["links"] as $link) {
                            $object_row["object_links"][] = [
                                "object_url" => htmlspecialchars($link["url"]),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($link["description"]))
                            ];
                        }
                    }
                    
                    $object_row["object_throws"] = [];
                    if (!empty($object["throws"])) {
                        foreach ($object["throws"] as $throw) {
                            $object_row["object_throws"][] = [
                                "object_item" => $this->linkIfExists($throw["type"]),
                                "object_description" => $this->formatText($object["source_file"]["uses_map"], checkempty($throw["description"]))
                            ];
                        }
                    }
                    
                    $objects[] = $object_row;
                }
            }
            explodeArray($iteration["type"], $objects, $contents);
            explodeArray("detailed_" . $iteration["type"], $objects, $contents);
        }
    } // generateTagsTexts
    
    protected function formatPhpStatement(&$object_descriptor, $text)
    {
        if (preg_match('/(.*function\s+[^()]+\()([^:]*)(\))(\s*:\s*(.*))?;/', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $func_part = $matches[1][0];
            $param_str = $matches[2][0];
            $offset = $matches[2][1];
            $result_part = checkempty($matches[5][0]);
            
            if (preg_match_all("/([^\\$,\s]+\s+)?(&|\.\.\.)?\\$[^\\$,\s]+/", $matches[2][0] . ",", $m2, PREG_OFFSET_CAPTURE)) {
                $cnt = count($m2[0]);
                for ($i = $cnt - 1; $i > 0; $i--) {
                    $pos = $m2[0][$i][1];
                    
                    $param_str = substr_replace($param_str, "\n" . str_repeat(" ", $offset), $pos, 0);
                }
                
                if (strlen($text) > 120) {
                    $text = $func_part;
                    $text .= $param_str . "\n";
                    $text .= str_repeat(" ", $offset - 1) . ") : " . $result_part . ";\n";
                }
            }
        }
        
        $highlighter = new \FSHL\Highlighter(new \FSHL\Output\Html());
        $highlighter->setLexer(new \FSHL\Lexer\Php());
        $highlighter->setOptions(\FSHL\Highlighter::OPTION_TAB_INDENT);
        
        $text = $highlighter->highlight($text);
        
        return $text;
    } // formatPhpStatement
    
    protected function resolveTypeName(&$uses, &$name)
    {
        $found = false;
        
        if (empty($name)) {
            $name = "";
            return $found;
        }
        
        foreach ($uses as $key => $full_name) {
            if (trim($key, "\\") == trim($name, "\\")) {
                $name = $full_name;
                $found = true;
                break;
            }
        }
        
        foreach ($this->dictionary["lookup"] as $full_name => &$object_descriptor) {
            if (preg_match("/.*" . preg_p_escape("\\" . ltrim($name, "\\")) . "$/", $full_name)) {
                $name = $full_name;
                $found = true;
                break;
            }
        }
        
        return $found;
    } // resolveTypeName
    
    protected function formatText(&$uses, $text)
    {
        $text = trim($text);
        
        if (empty($text)) {
            return $text;
        }
        
        $text = preg_replace_callback("/\{@(see|link)\s+([^\s\{\}]+)(\s+.*?)?\}/sm", function ($matches) use (&$uses) {
            if ($matches[1] == "see") {
                $full_name = $matches[2];
                if ($this->resolveTypeName($uses, $full_name)) {
                    $name = checkempty($matches[3]);
                    $name = trim($name);
                    if (empty($name)) {
                        $name = $full_name;
                    }
                    
                    $link = $this->getHelpFile($this->dictionary["lookup"][$full_name]) . $this->getAnchor($this->dictionary["lookup"][$full_name]);
                    
                    return "<a href='$link'>" . htmlspecialchars($name) . "</a>";
                } else {
                    $name = checkempty($matches[3]);
                    $name = trim($name);
                    if (empty($name)) {
                        return htmlspecialchars($full_name);
                    }
                    
                    return htmlspecialchars($full_name . " ($name)");
                }
            } elseif ($matches[1] == "link") {
                $name = checkempty($matches[3]);
                if (empty($name)) {
                    $name = checkempty($matches[2]);
                }
                
                return "<a href='$matches[2]' target='_blank'>" . htmlspecialchars($name) . "</a>";
            }
            
            return $matches[0];
        }, $text);
        
        $parsedown = new \ParsedownExtra();
        $parsedown->setUrlsLinked(false);
        
        $text = $parsedown->text($text);
        
        $text = preg_replace_callback("/<code class=\"language-php\">(.*?)<\\/code>/sm", function ($matches) {
            $highlighter = new \FSHL\Highlighter(new \FSHL\Output\Html());
            $highlighter->setLexer(new \FSHL\Lexer\Php());
            $highlighter->setOptions(\FSHL\Highlighter::OPTION_TAB_INDENT);
            
            return "<code class=\"language-php\">" . $highlighter->highlight(htmlspecialchars_decode($matches[1])) . "</code>";
        }, $text);
        
        return $text;
    } // formatText
    
    protected function countNotIgnored(&$object_list)
    {
        $cnt = 0;
        foreach ($object_list as &$object_descriptor) {
            if (empty($object_descriptor["ignore"])) {
                $cnt++;
            }
        }
        
        return $cnt;
    } // countNotIgnored
    
    public function generate(&$config)
    {
        echo_highlighted("\n\nHelp files generation started\n");
        
        $this->source_dir = rtrim($config["source"], "/\\");
        if (empty($this->source_dir)) {
            throw new \Exception("Source directory is not specified!");
        }
        $this->source_dir .= DIRECTORY_SEPARATOR;
        
        $this->target_dir = rtrim($config["target"], "/\\");
        if (empty($this->target_dir)) {
            throw new \Exception("Target directory is not specified!");
        }
        
        $this->target_dir .= DIRECTORY_SEPARATOR;
        
        $this->gen_date = date("Y-m-d H:i");
        
        $this->config = &$config;
        
        $this->template_dir = $config["template"] . DIRECTORY_SEPARATOR;
        if (!file_exists($this->template_dir)) {
            throw new \Exception("Template directory '$this->template_dir' does not exist!");
        }
        
        if (!file_exists($this->target_dir)) {
            if (!mkdir($this->target_dir)) {
                throw new \Exception("Error by creating directory: " . $this->target_dir);
            }
        }
        
        $this->clearDir($this->target_dir);
        
        if (!file_exists($this->target_dir . "resources")) {
            if (!mkdir($this->target_dir . "resources")) {
                throw new \Exception("Error by creating directory: " . $this->target_dir . "resources");
            }
        }
        
        $this->copyDirContents($this->template_dir . "resources", $this->target_dir . "resources");
        
        $this->generateIndexFile();
        
        $this->generateLogFile();
        
        $this->generateLookupJsFile();
        
        $this->generateSourceFiles();
        
        $this->generateNamespaceFiles();
        
        $this->generatePackageFiles();
        
        $this->generateConstantFiles();
        
        $this->generateGlobalVariableFiles();
        
        $this->generateFunctionFiles();
        
        $this->generateClassFiles();
        
        $this->generateInterfaceFiles();
        
        $this->generateTraitFiles();
        
        $this->generateUserFiles();
        
        echo_highlighted("\n\nHelp files generation completed");
    } // generate
} // PhpDocGenerator