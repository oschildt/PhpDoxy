<?php

namespace PhpDoxy;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;

/**
 * Renders and publishes the documentation site for a parsed PHP project.
 *
 * @author Oleg Schildt
 */
class PhpDocGenerator
{
    protected array $dictionary;
    protected array $config;
    protected string $target_dir;
    protected string $template_dir;
    protected string $gen_date;
    protected PhpHighlighter $highlighter;
    protected DocTypeRenderer $type_renderer;
    protected ReferenceResolver $references;
    protected MarkdownRenderer $markdown;
    protected array $markdown_pages = [];
    protected array $markdown_names = [];
    protected array $written_files = [];
    protected array $templates = [];

    /**
     * Initializes the HTML generator for the complete documentation model.
     *
     * @param array<string, array> $dictionary
     * The parsed documentation model, kept by reference for custom renderers.
     *
     * @author Oleg Schildt
     */
    public function __construct(array &$dictionary)
    {
        $this->dictionary = &$dictionary;
        $this->highlighter = new PhpHighlighter();
        $this->type_renderer = new DocTypeRenderer();
        $this->references = new ReferenceResolver($dictionary['lookup']);
        $this->markdown = new MarkdownRenderer();

        $this->markdown->setSafeMode(true);

        $this->markdown->setUrlsLinked(false);
    } // __construct

    /**
     * Copies the template resources into the new site.
     *
     * @param string $source_dir
     * The template resource directory.
     *
     * @param string $target_dir
     * The corresponding directory in the staging site.
     *
     * @return void
     * Copies resources while checking each read and write.
     *
     * @throws \RuntimeException
     * If a directory cannot be listed or a resource cannot be copied.
     *
     * @author Oleg Schildt
     */
    protected function copyDirContents(string $source_dir, string $target_dir): void
    {
        $source = realpath($source_dir);

        if ($source === false || !is_dir($source)) {
            throw new \RuntimeException("Cannot read resource directory '$source_dir'.");
        }

        foreach (new \FilesystemIterator($source) as $entry) {
            $path = $entry->getPathname();
            $target = $target_dir . DIRECTORY_SEPARATOR . $entry->getFilename();
            $resolved = realpath($path);

            if ($resolved === false || $entry->isLink() || !OutputDirectory::samePath($path, $resolved)) {
                throw new \RuntimeException("Resource '$path' must be a regular file or directory.");
            }

            if ($entry->isDir()) {
                if (!@mkdir($target)) {
                    throw new \RuntimeException("Cannot create resource directory '$target'.");
                }

                $this->copyDirContents($path, $target);
            } elseif (!$entry->isFile() || !@copy($path, $target)) {
                throw new \RuntimeException("Cannot copy resource '$path'.");
            }
        }
    } // copyDirContents

    protected function isEmptyGlobalNamespase(array &$object_descriptor): bool
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

    protected function removeNamespacePrefix(string $name): string
    {
        return preg_replace("/.*\\\\/", "", $name);
    } // removeNamespacePrefix



    /**
     * Returns the label used for a declaration kind in lists and reports.
     *
     * @param string $type
     * The declaration kind from the model.
     *
     * @return string
     * Returns the display label, or the original value for a custom kind.
     *
     * @author Oleg Schildt
     */
    protected function getNameByType(string $type): string
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

            case "enum":
                $type = "Enum";
                break;

            case "enum_case":
                $type = "Enum case";
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

    protected function objNameToFileName(string $name): string
    {
        $name = trim($name, "/\\()");
        $name = str_replace("\\", ".", $name);
        $name = str_replace("/", ".", $name);

        return $name;
    } // objNameToFileName

    /**
     * Encodes source path segments distinctly for a flat output directory.
     *
     * @param string $path
     * The relative source path without its extension, including any source directory label.
     *
     * @return string
     * Returns a URL-safe name that distinguishes literal dots from directory separators.
     *
     * @author Oleg Schildt
     */
    protected function flattenSourcePath(string $path): string
    {
        $escaped = preg_replace_callback(
            '~[^a-z0-9/_-]~i',
            static fn (array $match): string => '~' . bin2hex($match[0]),
            $path
        );

        return str_replace('/', '.', $escaped);
    } // flattenSourcePath

    /**
     * Maps a documented declaration to the page that contains it.
     *
     * @param array<string, mixed> $object_descriptor
     * The documented declaration or source file.
     *
     * @return string
     * Returns the filename relative to the output directory, with source paths qualified in multi-directory sites.
     *
     * @author Oleg Schildt
     */
    protected function getHelpFile(array &$object_descriptor): string
    {
        $file_name = $this->objNameToFileName($object_descriptor["full_name"]);
        $prefix = "";

        switch ($object_descriptor["type"]) {
            case "enum_case":
            case "class_constant":
            case "method":
            case "property":
                return $this->getHelpFile($object_descriptor["parent"]);

            case "source_file":
                $prefix = "source-";

                if (($object_descriptor['source_label'] ?? '') !== '') {
                    $source_path = $object_descriptor['full_name'];
                    $file_name = $this->flattenSourcePath(substr($source_path, 0, -4)) . substr($source_path, -4);
                }

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

            case "enum":
                $prefix = "enum-";
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

    /**
     * Returns the fragment identifying a member within its documentation page.
     *
     * @param array<string, mixed> $object_descriptor
     * The documented declaration or source file.
     *
     * @return string
     * Returns the fragment including its hash, or an empty string for a page.
     *
     * @author Oleg Schildt
     */
    protected function getAnchor(array $object_descriptor): string
    {
        $anchor = "";
        switch ($object_descriptor["type"]) {
            case "enum_case":
            case "class_constant":
            case "method":
            case "property":
                $anchor = "#" . trim($object_descriptor["short_name"], "/\\()");
                break;
        }

        return $anchor;
    } // getAnchor

    protected function getLabel(array $object_descriptor): string
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

    /**
     * Reads a template once per generation.
     *
     * @param string $template_file
     * The template filename relative to the configured template directory.
     *
     * @return string
     * Returns the template text.
     *
     * @throws \RuntimeException
     * If the template cannot be read.
     *
     * @author Oleg Schildt
     */
    protected function getTemplate(string $template_file): string
    {
        if (isset($this->templates[$template_file])) {
            return $this->templates[$template_file];
        }

        $path = $this->template_dir . $template_file;
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Cannot read template '$path'.");
        }

        return $this->templates[$template_file] = $contents;
    } // getTemplate

    /**
     * Resolves page placeholders in the template before inserting rendered content.
     *
     * @param string $template_file
     * The page template filename relative to the configured template directory.
     *
     * @param array<string, string> $replacements
     * The page-specific values keyed by case-insensitive placeholder names, including braces.
     *
     * @return string
     * Returns the page template with its page-specific placeholders replaced.
     *
     * @throws \RuntimeException
     * If the page template cannot be read.
     *
     * @author Oleg Schildt
     */
    protected function getPageTemplate(string $template_file, array $replacements): string
    {
        $template = $this->getTemplate($template_file);
        $values = array_change_key_case($replacements) + ['{gen_date}' => $this->gen_date];

        return preg_replace_callback(
            '/\{[a-z_][a-z0-9_]*\}/i',
            static fn (array $match): string => $values[strtolower($match[0])] ?? $match[0],
            $template
        );
    } // getPageTemplate

    protected function renderNamespaceList(array &$object_list, string &$contents, bool $deep = false): void
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

    protected function renderPackageList(array &$object_list, string &$contents): void
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

    protected function renderConstantList(array &$object_list, string &$contents): void
    {
        $constant_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $row = [
                "constant_name" => htmlspecialchars($object_descriptor["name"]),
                "constant_link" => $this->getHelpFile($object_descriptor),
                "constant_description" => $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "")
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

    protected function renderGlobalVariableList(array &$object_list, string &$contents): void
    {
        $glovar_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $glovar_array[] = [
                "global_name" => htmlspecialchars($object_descriptor["name"]),
                "global_link" => $this->getHelpFile($object_descriptor),
                "global_description" => $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "")
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
            $object_item = str_ireplace("{global_description}", $this->formatText($object_descriptor, $object_descriptor["summary"] ?? ""), $object_item);

            $object_list_contents .= $object_item;
        }

        $object_list_body = str_replace($object_sample, $object_list_contents, $object_list_body);

        $contents = str_replace($object_list_sample, $object_list_body, $contents);
    } // renderGlobalVariableList

    protected function renderFunctionList(array &$object_list, string &$contents): void
    {
        $function_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $row = [
                "function_name" => htmlspecialchars($object_descriptor["name"]),
                "function_link" => $this->getHelpFile($object_descriptor),
                "function_description" => $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "")
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

    protected function renderInterfaceList(array &$object_list, string &$contents): void
    {
        $interface_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $row = [
                "interface_name" => htmlspecialchars($object_descriptor["name"]),
                "interface_link" => $this->getHelpFile($object_descriptor),
                "interface_description" => $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "")
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

    protected function renderClassList(array &$object_list, string &$contents): void
    {
        $class_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $row = [
                "class_name" => htmlspecialchars($object_descriptor["name"]),
                "class_link" => $this->getHelpFile($object_descriptor),
                "class_description" => $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "")
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

    protected function renderTraitList(array &$object_list, string &$contents): void
    {
        $trait_array = [];
        foreach ($object_list as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $row = [
                "trait_name" => htmlspecialchars($object_descriptor["name"]),
                "trait_link" => $this->getHelpFile($object_descriptor),
                "trait_description" => $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "")
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

    protected function renderBreadcrumbs(string &$layout_contents, string $current_object = ""): void
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

    protected function renderMenu(string &$layout_contents, string $file_name): void
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

    /**
     * Applies the site layout and writes a page to the staging directory.
     *
     * @param string $file_name
     * The relative output filename.
     *
     * @param string $contents
     * The rendered page body with its page-specific placeholders resolved.
     *
     * @param array<string, string> $replacements
     * The page's layout placeholders.
     *
     * @param string $current_object
     * The full name to use for breadcrumbs, if applicable.
     *
     * @return void
     * Writes the complete page.
     *
     * @throws \RuntimeException
     * If the filename is unsafe, already used, or cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function createFile(string $file_name, string $contents, array &$replacements, string $current_object = ""): void
    {
        $layout_contents = $this->getTemplate("layout.tpl");

        $this->renderMenu($layout_contents, $file_name);

        $this->renderBreadcrumbs($layout_contents, $current_object);

        $replacements["{gen_date}"] = $this->gen_date;
        $replacements["{contents}"] = $contents;
        $layout_contents = strtr($layout_contents, $replacements);

        $this->writeFile($file_name, $layout_contents);
    } // createFile

    /**
     * Writes one output file and detects collisions before publication.
     *
     * @param string $file_name
     * A single filename within the staging directory.
     *
     * @param string $contents
     * The complete output contents.
     *
     * @return void
     * Writes the file after validating that it is a new output.
     *
     * @throws \RuntimeException
     * If the name refers outside the staging directory, is reused, or cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function writeFile(string $file_name, string $contents): void
    {
        if ($file_name === '' || strpbrk($file_name, "/\\\0") !== false || in_array($file_name, ['.', '..'], true)) {
            throw new \RuntimeException("Invalid generated filename '$file_name'.");
        }

        if (DIRECTORY_SEPARATOR === '\\' && (strpbrk($file_name, ':<>|?*"') !== false || rtrim($file_name, ' .') !== $file_name)) {
            throw new \RuntimeException("Invalid Windows filename '$file_name'.");
        }

        $key = DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($file_name, 'UTF-8') : $file_name;
        $path = $this->target_dir . $file_name;

        if (isset($this->written_files[$key]) || file_exists($path)) {
            throw new \RuntimeException("Multiple documentation files use the filename '$file_name'.");
        }

        if (@file_put_contents($path, $contents) !== strlen($contents)) {
            throw new \RuntimeException("Cannot write documentation file '$path'.");
        }

        $this->written_files[$key] = true;
    } // writeFile

    /**
     * Writes the JavaScript lookup used by the site search.
     *
     * @return void
     * Writes search records for each published symbol.
     *
     * @throws \RuntimeException
     * If the lookup cannot be written.
     *
     * @throws \JsonException
     * If a lookup entry cannot be encoded as JSON.
     *
     * @author Oleg Schildt
     */
    protected function generateLookupJsFile(): void
    {
        echo_standard("\nCreating JS lookup file");

        $entries = [];

        foreach ($this->dictionary['lookup'] as $name => $descriptor) {
            $href = $this->symbolHref($name);

            if ($href !== null) {
                $entries[] = ['file' => $href, 'label' => htmlspecialchars($this->getLabel($descriptor))];
            }
        }

        $json = json_encode($entries, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        $contents = "var ApiGen = ApiGen || {};\nApiGen.elements = " . $json . ";\n";

        $this->writeFile('elementlist.js', $contents);
    } // generateLookupJsFile

    /**
     * Generates pages from the configured user-supplied HTML files.
     *
     * @throws \Exception
     * If a user file cannot be read.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateUserFiles(): void
    {
        echo_standard("\nCreating user defined files");

        if (empty($this->config["user_files"])) {
            return;
        }

        foreach ($this->config["user_files"] as &$user_file) {
            $replacements = [];
            $replacements["{title}"] = $user_file["title"] . " - " . htmlspecialchars($this->config["title"]);
            $replacements["{header_title}"] = $user_file["title"];

            $body = file_get_contents($user_file["path"]);

            if ($body === false) {
                throw new \Exception("File '" . $user_file["path"] . "' is not readable!");
            }

            $contents = $this->getPageTemplate('user_content.tpl', $replacements + ['{contents}' => $body]);

            $this->createFile(basename($user_file["path"]), $contents, $replacements);
        }
    } // generateUserFiles

    /**
     * Publishes Markdown pages with their original headings and a browser title from the opening heading or filename.
     *
     * @throws \RuntimeException
     * If a Markdown document or the site layout cannot be read, or a page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateMarkdownFiles(): void
    {
        $files = $this->dictionary['markdown_files'] ?? [];

        if ($files === []) {
            return;
        }

        echo_standard("\nCreating Markdown pages");

        foreach ($files as $relative => $file) {
            $text = @file_get_contents($file);

            if ($text === false) {
                throw new \RuntimeException("Cannot read Markdown file '$relative'.");
            }

            if (str_starts_with($text, "\xEF\xBB\xBF")) {
                $text = substr($text, 3);
            }

            $body = $this->renderMarkdown($text, directory: dirname($file));
            $title = '';

            if (preg_match('~\A\s*#~', $text)
                && preg_match('~\A<h([1-3])\b[^>]*>(.*?)</h\1>~s', $body, $heading)
            ) {
                $title = trim(html_entity_decode(strip_tags($heading[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if ($title === '') {
                $title = substr(basename($relative), 0, -3);
            }

            $replacements = [
                '{title}' => htmlspecialchars($title) . ' - ' . htmlspecialchars($this->config['title']),
                '{header_title}' => '',
            ];
            $contents = '<div class="markdown-content">' . $body . '</div>';

            $this->createFile($this->markdown_pages[$this->markdownPathKey($file)], $contents, $replacements);
        }
    } // generateMarkdownFiles

    /**
     * Indexes generated filenames by source path and records unambiguous basenames.
     *
     * @author Oleg Schildt
     */
    protected function indexMarkdownPages(): void
    {
        $this->markdown_pages = [];
        $this->markdown_names = [];

        foreach ($this->dictionary['markdown_files'] ?? [] as $relative => $file) {
            $page = 'markdown-' . $this->flattenSourcePath(substr($relative, 0, -3)) . substr($relative, -3) . '.html';
            $name = $this->markdownPathKey(basename($relative));
            $this->markdown_pages[$this->markdownPathKey($file)] = $page;
            $this->markdown_names[$name] = array_key_exists($name, $this->markdown_names) ? null : $page;
        }
    } // indexMarkdownPages

    /**
     * Normalizes a Markdown lookup key for the platform's path comparison.
     *
     * @param string $path
     * A canonical source path or a Markdown filename.
     *
     * @return string
     * Returns a case-folded key on Windows and the original key elsewhere.
     *
     * @author Oleg Schildt
     */
    protected function markdownPathKey(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($path, 'UTF-8') : $path;
    } // markdownPathKey

    /**
     * Maps Markdown references to generated pages while retaining their URL suffix.
     *
     * @param string $url
     * A Markdown link destination, optionally prefixed with `markdown:`.
     *
     * @param string|null $directory
     * The absolute directory of the current source, if available.
     *
     * @return string|null
     * Returns the generated URL or the original ordinary URL. Explicit references return null if unresolved.
     *
     * @author Oleg Schildt
     */
    protected function resolveMarkdownLink(string $url, ?string $directory): ?string
    {
        $explicit = str_starts_with($url, 'markdown:');
        $parts = parse_url($explicit ? substr($url, strlen('markdown:')) : $url);
        $path = rawurldecode($parts['path'] ?? '');

        if (isset($parts['scheme']) || isset($parts['host']) || str_starts_with($path, '/') || str_starts_with($path, '\\')
            || str_contains($path, "\0") || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'md'
        ) {
            return $explicit ? null : $url;
        }

        $file = $directory === null ? false : realpath($directory . DIRECTORY_SEPARATOR . $path);
        $page = $file === false ? null : ($this->markdown_pages[$this->markdownPathKey($file)] ?? null);

        if ($page === null && $explicit && !str_contains($path, '/') && !str_contains($path, '\\')) {
            $page = $this->markdown_names[$this->markdownPathKey($path)] ?? null;
        }

        if ($page === null) {
            return $explicit ? null : $url;
        }

        return $page . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    } // resolveMarkdownLink

    /**
     * Locates the directory of the PHP source containing a declaration's documentation.
     *
     * @param array<string, mixed> $scope
     * A declaration descriptor, including its source file when known.
     *
     * @return string|null
     * Returns the directory used to resolve relative links, or null if no source is known.
     *
     * @author Oleg Schildt
     */
    protected function sourceDirectory(array $scope): ?string
    {
        $source = $scope['source_file'] ?? [];

        if (!isset($source['source_root'], $source['relative_path'])) {
            return null;
        }

        $relative = $source['relative_path'];

        if (!empty($source['source_label'])) {
            $relative = substr($relative, strlen($source['source_label']) + 1);
        }

        return dirname(rtrim($source['source_root'], '/\\') . DIRECTORY_SEPARATOR . $relative);
    } // sourceDirectory

    /**
     * Renders source files with anchors matching the original physical line numbers.
     *
     * @return void
     * Writes a source page for each parsed file.
     *
     * @throws \RuntimeException
     * If a source page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateSourceFiles(): void
    {
        echo_standard("\nCreating source files");

        foreach ($this->dictionary['source_files'] as &$descriptor) {
            $highlighted = $this->highlighter->highlight($descriptor['code'], lineNumbers: true);
            $name = htmlspecialchars($descriptor['full_name']);
            $replacements = [
                '{title}' => 'Source Code: ' . $name . ' - ' . htmlspecialchars($this->config['title']),
                '{header_title}' => 'Source Code: ' . $name,
            ];
            $contents = $this->getPageTemplate('source_code.tpl', $replacements + ['{contents}' => $highlighted]);

            $this->createFile($this->getHelpFile($descriptor), $contents, $replacements);
        }
    } // generateSourceFiles

    /**
     * Generates the namespace pages and their declaration indexes.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateNamespaceFiles(): void
    {
        echo_standard("\nCreating namespace files");

        foreach ($this->dictionary["namespaces"] as &$object_descriptor) {
            if ($this->isEmptyGlobalNamespase($object_descriptor)) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Namespace: " . $object_descriptor["name"];
            $replacements["{header_title}"] = "Namespace: " . $object_descriptor["name"];

            $namespace_contents = $this->getPageTemplate('namespace.tpl', $replacements);

            $this->renderNamespaceList($object_descriptor["children"]["namespaces"], $namespace_contents, true);

            $this->renderConstantList($object_descriptor["children"]["constants"], $namespace_contents);

            $this->renderFunctionList($object_descriptor["children"]["functions"], $namespace_contents);

            $this->renderInterfaceList($object_descriptor["children"]["interfaces"], $namespace_contents);

            $this->renderClassList($object_descriptor["children"]["classes"], $namespace_contents);

            $this->renderTraitList($object_descriptor["children"]["traits"], $namespace_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $namespace_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateNamespaceFiles

    /**
     * Generates the package pages and their declaration indexes.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generatePackageFiles(): void
    {
        echo_standard("\nCreating package files");

        foreach ($this->dictionary["packages"] as &$object_descriptor) {
            if (empty($object_descriptor["name"])) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Package: " . $object_descriptor["name"];
            $replacements["{header_title}"] = "Package: " . $object_descriptor["name"];

            $package_contents = $this->getPageTemplate('package.tpl', $replacements);

            $this->renderConstantList($object_descriptor["children"]["constants"], $package_contents);

            $this->renderGlobalVariableList($object_descriptor["children"]["global_variables"], $package_contents);

            $this->renderFunctionList($object_descriptor["children"]["functions"], $package_contents);

            $this->renderInterfaceList($object_descriptor["children"]["interfaces"], $package_contents);

            $this->renderClassList($object_descriptor["children"]["classes"], $package_contents);

            $this->renderTraitList($object_descriptor["children"]["traits"], $package_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $package_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generatePackageFiles

    /**
     * Writes the class index and pages for classes and enums.
     *
     * @return void
     * Publishes the declarations under their respective page names.
     *
     * @author Oleg Schildt
     */
    protected function generateClassFiles(): void
    {
        echo_standard("\nCreating class files");

        if ($this->countNotIgnored($this->dictionary["classes"]) == 0) {
            return;
        }

        $replacements = [];
        $replacements["{title}"] = "Classes - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Classes - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('classes.tpl', $replacements);

        $this->renderClassList($this->dictionary["classes"], $contents);

        $this->createFile("classes.html", $contents, $replacements);

        foreach ($this->dictionary["classes"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $replacements = [];
            $label = $object_descriptor['type'] === 'enum' ? 'Enum: ' : 'Class: ';
            $replacements["{title}"] = $label . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = $label . htmlspecialchars($object_descriptor["name"]);

            $constant_contents = $this->getPageTemplate('class.tpl', $replacements);

            $this->generateTagsTexts($object_descriptor, $constant_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateClassFiles

    /**
     * Generates the interface index and individual interface pages.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateInterfaceFiles(): void
    {
        echo_standard("\nCreating interface files");

        if ($this->countNotIgnored($this->dictionary["interfaces"]) == 0) {
            return;
        }

        $replacements = [];
        $replacements["{title}"] = "Interfaces - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Interfaces - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('interfaces.tpl', $replacements);

        $this->renderInterfaceList($this->dictionary["interfaces"], $contents);

        $this->createFile("interfaces.html", $contents, $replacements);

        foreach ($this->dictionary["interfaces"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Interface: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Interface: " . htmlspecialchars($object_descriptor["name"]);

            $constant_contents = $this->getPageTemplate('interface.tpl', $replacements);

            $this->generateTagsTexts($object_descriptor, $constant_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateInterfaceFiles

    /**
     * Generates the trait index and individual trait pages.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateTraitFiles(): void
    {
        echo_standard("\nCreating trait files");

        if ($this->countNotIgnored($this->dictionary["traits"]) == 0) {
            return;
        }

        $replacements = [];
        $replacements["{title}"] = "Traits - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Traits - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('traits.tpl', $replacements);

        $this->renderTraitList($this->dictionary["traits"], $contents);

        $this->createFile("traits.html", $contents, $replacements);

        foreach ($this->dictionary["traits"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Trait: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Trait: " . htmlspecialchars($object_descriptor["name"]);

            $constant_contents = $this->getPageTemplate('trait.tpl', $replacements);

            $this->generateTagsTexts($object_descriptor, $constant_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateTraitFiles

    /**
     * Generates the function index and individual function pages.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateFunctionFiles(): void
    {
        echo_standard("\nCreating function files");

        if ($this->countNotIgnored($this->dictionary["functions"]) == 0) {
            return;
        }

        $replacements = [];
        $replacements["{title}"] = "Functions - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Functions - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('functions.tpl', $replacements);

        $this->renderFunctionList($this->dictionary["functions"], $contents);

        $this->createFile("functions.html", $contents, $replacements);

        foreach ($this->dictionary["functions"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Function: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Function: " . htmlspecialchars($object_descriptor["name"]);

            $constant_contents = $this->getPageTemplate('function.tpl', $replacements);

            $this->generateTagsTexts($object_descriptor, $constant_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateFunctionFiles

    /**
     * Generates the constant index and individual constant pages.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateConstantFiles(): void
    {
        if ($this->countNotIgnored($this->dictionary["constants"]) == 0) {
            return;
        }

        $replacements = [];
        $replacements["{title}"] = "Constants - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Constants - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('constants.tpl', $replacements);

        $this->renderConstantList($this->dictionary["constants"], $contents);

        $this->createFile("constants.html", $contents, $replacements);

        foreach ($this->dictionary["constants"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Constant: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Constant: " . htmlspecialchars($object_descriptor["name"]);

            $constant_contents = $this->getPageTemplate('constant.tpl', $replacements);

            $this->generateTagsTexts($object_descriptor, $constant_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $constant_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateConstantFiles

    /**
     * Renders the changelog, deprecations, and to-do entries.
     *
     * @return void
     * Writes the log page for the current site.
     *
     * @author Oleg Schildt
     */
    protected function generateLogFile(): void
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

        $contents = $this->getPageTemplate('log.tpl', $replacements);

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
                        "object_description" => $this->formatText($object_descriptor, $since["description"] ?? "")
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

            $object_row['object_deprecated'] = [[
                'object_version' => htmlspecialchars($object_descriptor['deprecated_version'] ?? ''),
                'object_description' => $this->formatText($object_descriptor, $object_descriptor['deprecated_description'] ?? ''),
            ]];

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
                        "object_description" => $this->formatText($object_descriptor, $todo["description"] ?? "")
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

    /**
     * Generates the global variable index and individual variable pages.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateGlobalVariableFiles(): void
    {
        echo_standard("\nCreating global variable files");

        if ($this->countNotIgnored($this->dictionary["global_variables"]) == 0) {
            return;
        }

        $replacements = [];
        $replacements["{title}"] = "Global variables - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Global variables - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('globals.tpl', $replacements);

        $this->renderGlobalVariableList($this->dictionary["global_variables"], $contents);

        $this->createFile("globals.html", $contents, $replacements);

        foreach ($this->dictionary["global_variables"] as &$object_descriptor) {
            if (!empty($object_descriptor["ignore"])) {
                continue;
            }

            $replacements = [];
            $replacements["{title}"] = "Global variable: " . htmlspecialchars($object_descriptor["name"]);
            $replacements["{header_title}"] = "Global variable: " . htmlspecialchars($object_descriptor["name"]);

            $global_contents = $this->getPageTemplate('global.tpl', $replacements);

            $this->generateTagsTexts($object_descriptor, $global_contents);

            $this->createFile($this->getHelpFile($object_descriptor), $global_contents, $replacements, $object_descriptor["full_name"]);
        }
    } // generateGlobalVariableFiles

    /**
     * Generates the documentation overview and its declaration indexes.
     *
     * @throws \RuntimeException
     * If a template cannot be read or the generated page cannot be written.
     *
     * @author Oleg Schildt
     */
    protected function generateIndexFile(): void
    {
        echo_standard("\nCreating index file");

        $replacements = [];
        $replacements["{title}"] = "Overview - " . htmlspecialchars($this->config["title"]);
        $replacements["{header_title}"] = "Overview - " . htmlspecialchars($this->config["title"]);

        $contents = $this->getPageTemplate('index.tpl', $replacements);

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

    /**
     * Renders a resolved symbol or an external HTTP link.
     *
     * @param string $type_name
     * The complete reference as stored in the documentation model.
     *
     * @return string
     * Returns the rendered link, or escaped text when no page exists.
     *
     * @author Oleg Schildt
     */
    protected function linkIfExists(string $type_name): string
    {
        $href = preg_match('~^https?://~i', $type_name) ? $type_name : $this->symbolHref($type_name);
        $label = htmlspecialchars($type_name);

        return $href === null ? $label : '<a href="' . htmlspecialchars($href) . '">' . $label . '</a>';
    } // linkIfExists

    /**
     * Looks up a published page and optional member anchor for a symbol.
     *
     * @param string $name
     * The exact dictionary key for the symbol.
     *
     * @return string|null
     * Returns the relative URL, or null when the symbol or its parent is unavailable.
     *
     * @author Oleg Schildt
     */
    protected function symbolHref(string $name): ?string
    {
        $descriptor = $this->dictionary['lookup'][$name] ?? null;

        if ($descriptor === null || !empty($descriptor['ignore']) || !empty($descriptor['parent']['ignore'])) {
            return null;
        }

        return $this->getHelpFile($descriptor) . $this->getAnchor($descriptor);
    } // symbolHref

    /**
     * Renders the displayed type as monospaced code, preserving its structural line breaks.
     *
     * @param string $native
     * The original PHP type or an empty string.
     *
     * @param TypeNode|null $documented
     * The optional PHPDoc refinement.
     *
     * @param array<string, mixed> $scope
     * The declaration that provides imports and template parameters.
     *
     * @return string
     * Returns a code element with links for documented class names, or an empty string when no type is available.
     *
     * @author Oleg Schildt
     */
    protected function formatType(string $native, ?TypeNode $documented, array $scope): string
    {
        if ($documented === null && $native === '') {
            return '';
        }

        $href = function (string $name) use ($scope, $documented): ?string {
            if ($documented !== null && (isset($scope['templates'][$name]) || isset($scope['parent']['templates'][$name]))) {
                return null;
            }

            $reference = $this->references->resolve($name, $scope, true);

            return $reference === null ? null : $this->symbolHref($reference);
        };

        $rendered = $documented === null
            ? $this->type_renderer->renderNative($native, $href)
            : $this->type_renderer->render($documented, $href);

        return '<code class="php-type">' . $rendered . '</code>';
    } // formatType

    /**
     * Renders the bounds and defaults of declared PHPDoc template parameters.
     *
     * @param array<string, mixed> $scope
     * The declaration whose type parameters are being displayed.
     *
     * @return string
     * Returns an HTML list of templates or an empty string.
     *
     * @author Oleg Schildt
     */
    protected function formatTemplates(array $scope): string
    {
        if (empty($scope['templates'])) {
            return '';
        }

        $list = '';

        foreach ($scope['templates'] as $template) {
            $item = htmlspecialchars($template->name);

            foreach (['bound' => ' of ', 'lowerBound' => ' super ', 'default' => ' = '] as $field => $prefix) {
                if ($template->$field !== null) {
                    $item .= $prefix . $this->formatType('', $template->$field, $scope);
                }
            }

            $list .= '<li><span class="php-type">' . $item . '</span>'
                . $this->formatText($scope, $template->description) . '</li>';
        }

        return '<h2>Type parameters</h2><ul class="type-parameters">' . $list . '</ul>';
    } // formatTemplates

    /**
     * Fills a declaration template with metadata, its native definition, and documented types.
     *
     * @param array<string, mixed> $object_descriptor
     * The declaration and its child members.
     *
     * @param string $contents
     * The template whose placeholders are replaced in place.
     *
     * @return void
     * Renders the declaration into the supplied template.
     *
     * @author Oleg Schildt
     */
    protected function generateTagsTexts(array &$object_descriptor, string &$contents): void
    {
        if (str_contains($contents, '{members}')) {
            $members = $this->getTemplate('members.tpl');
            $heading = $object_descriptor['type'] === 'enum' ? 'Cases and constants' : 'Constants';
            $contents = str_replace('{members}', str_replace('{constant_member_heading}', $heading, $members), $contents);
        }

        $object_row = [];
        $object_row["definition"] = $this->formatPhpStatement($object_descriptor, $object_descriptor["php_statement"] ?? "");

        $object_row["description"] = $this->formatText($object_descriptor, $object_descriptor["summary"] ?? "");
        $object_row["long_description"] = $this->formatText($object_descriptor, $object_descriptor["description"] ?? "");

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

        $object_row['type'] = $this->formatType($object_descriptor['val_type'] ?? '', $object_descriptor['doc_type'] ?? null, $object_descriptor);
        $object_row['return_type'] = $this->formatType($object_descriptor['return_type'] ?? '', $object_descriptor['return_doc_type'] ?? null, $object_descriptor);
        $object_row['type_parameters'] = $this->formatTemplates($object_descriptor);
        $object_row["return_description"] = $this->formatText($object_descriptor, $object_descriptor["return_description"] ?? "");

        if (!empty($object_descriptor["copyright"])) {
            $object_row["copyright"] = $this->formatText($object_descriptor, $object_descriptor["copyright"]);
        } else {
            $object_row["copyright"] = "";
        }

        if (!empty($object_descriptor["license"])) {
            $object_row["license"] = $this->formatText($object_descriptor, $object_descriptor["license"]);
        } else {
            $object_row["license"] = "";
        }

        $object_row['deprecated'] = !empty($object_descriptor['deprecated']);
        $object_row['deprecated_version'] = htmlspecialchars($object_descriptor['deprecated_version'] ?? '');
        $object_row["deprecated_description"] = $this->formatText($object_descriptor, $object_descriptor["deprecated_description"] ?? "");

        if (!empty($object_descriptor["version"])) {
            $object_row["version"] = $object_descriptor["version"];
        } else {
            $object_row["version"] = "";
        }
        $object_row["version_description"] = $this->formatText($object_descriptor, $object_descriptor["version_description"] ?? "");

        if (isset($object_descriptor["internal"])) {
            if (empty($object_descriptor["internal"])) {
                $object_row["internal"] = "Internal use only!";
            } else {
                $object_row["internal"] = $this->formatText($object_descriptor, $object_descriptor["internal"]);
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
                    "param_type" => $this->formatType($param['val_type'], $param['doc_type'] ?? null, $object_descriptor),
                    "param_default" => htmlspecialchars($param["default_value"] ?? ""),
                    "description" => $this->formatText($object_descriptor, $param["description"] ?? "")
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
                    "description" => $this->formatText($object_descriptor, $see_data["description"] ?? "")
                ];
            }
        }
        explodeArray("see", $see, $contents);

        $uses = [];
        if (!empty($object_descriptor["uses"])) {
            foreach ($object_descriptor["uses"] as $uses_data) {
                $uses[] = [
                    "item" => $this->linkIfExists($uses_data["reference"]),
                    "description" => $this->formatText($object_descriptor, $uses_data["description"] ?? "")
                ];
            }
        }
        explodeArray("uses", $uses, $contents);

        $used_by = [];
        if (!empty($object_descriptor["used-by"])) {
            foreach ($object_descriptor["used-by"] as $used_by_data) {
                $used_by[] = [
                    "item" => $this->linkIfExists($used_by_data["reference"]),
                    "description" => $this->formatText($object_descriptor, $used_by_data["description"] ?? "")
                ];
            }
        }
        explodeArray("used_by", $used_by, $contents);

        $sinces = [];
        if (!empty($object_descriptor["since"])) {
            foreach ($object_descriptor["since"] as $since) {
                $sinces[] = [
                    "version" => htmlspecialchars($since["version"]),
                    "description" => $this->formatText($object_descriptor, $since["description"] ?? "")
                ];
            }
        }
        explodeArray("since", $sinces, $contents);

        $todos = [];
        if (!empty($object_descriptor["todos"])) {
            foreach ($object_descriptor["todos"] as $todo) {
                $todos[] = [
                    "description" => $this->formatText($object_descriptor, $todo["description"] ?? "")
                ];
            }
        }
        explodeArray("todos", $todos, $contents);

        $links = [];
        if (!empty($object_descriptor["links"])) {
            foreach ($object_descriptor["links"] as $link) {
                $links[] = $this->formatLinkTag($object_descriptor, $link);
            }
        }
        explodeArray("links", $links, $contents);

        $throws = [];
        if (!empty($object_descriptor["throws"])) {
            foreach ($object_descriptor["throws"] as $throw) {
                $throws[] = [
                    "item" => $this->formatType('', $throw['doc_type'], $object_descriptor),
                    "description" => $this->formatText($object_descriptor, $throw["description"] ?? "")
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
                    if (!empty($object['ignore'])) {
                        continue;
                    }

                    $member_label = $object['type'] === 'enum_case' ? 'Case' : $iteration['name'];
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
                        "object_title" => $member_label . ": " . $object["name"],
                        "object_link" => $this->getAnchor($object),
                        "object_description" => $this->formatText($object, $object["summary"] ?? ""),
                        "object_long_description" => $this->formatText($object, $object["description"] ?? ""),
                        "object_definition" => $this->formatPhpStatement($object, $object["php_statement"] ?? ""),
                        "object_visibility" => $object_visibility
                    ];

                    $object_row["source-file"] = htmlspecialchars($object["source_file"]["relative_path"]);
                    if (!empty($object_row["source-file"])) {
                        $href = $this->getHelpFile($object["source_file"]) . "#" . $object["start_line"];
                        $object_row["object_source-file"] = "<a href='$href'>" . $object_row["source-file"] . "</a>";
                    }

                    $object_row['object_type'] = $this->formatType($object['val_type'] ?? '', $object['doc_type'] ?? null, $object);
                    $object_row['object_return_type'] = $this->formatType($object['return_type'] ?? '', $object['return_doc_type'] ?? null, $object);
                    $object_row['object_type_parameters'] = $this->formatTemplates($object);
                    $object_row["object_return_description"] = $this->formatText($object, $object["return_description"] ?? "");

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

                    $object_row['object_deprecated'] = !empty($object['deprecated']);
                    $object_row['object_deprecated_version'] = htmlspecialchars($object['deprecated_version'] ?? '');
                    $object_row["object_deprecated_description"] = $this->formatText($object, $object["deprecated_description"] ?? "");

                    if (!empty($object["version"])) {
                        $object_row["object_version"] = $object["version"];
                    } else {
                        $object_row["object_version"] = "";
                    }
                    $object_row["object_version_description"] = $this->formatText($object, $object["version_description"] ?? "");

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
                                "object_param_type" => $this->formatType($param['val_type'], $param['doc_type'] ?? null, $object),
                                "object_param_default" => htmlspecialchars($param["default_value"] ?? ""),
                                "object_description" => $this->formatText($object, $param["description"] ?? "")
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
                                "object_description" => $this->formatText($object, $see_data["description"] ?? "")
                            ];
                        }
                    }

                    $object_row["object_uses"] = [];
                    if (!empty($object["uses"])) {
                        foreach ($object["uses"] as $uses_data) {
                            $object_row["object_uses"][] = [
                                "object_item" => $this->linkIfExists($uses_data["reference"]),
                                "object_description" => $this->formatText($object, $uses_data["description"] ?? "")
                            ];
                        }
                    }

                    $object_row["object_used_by"] = [];
                    if (!empty($object["used-by"])) {
                        foreach ($object["used-by"] as $used_by_data) {
                            $object_row["object_used_by"][] = [
                                "object_item" => $this->linkIfExists($used_by_data["reference"]),
                                "object_description" => $this->formatText($object, $used_by_data["description"] ?? "")
                            ];
                        }
                    }

                    $object_row["object_since"] = [];
                    if (!empty($object["since"])) {
                        foreach ($object["since"] as $since) {
                            $object_row["object_since"][] = [
                                "object_version" => htmlspecialchars($since["version"]),
                                "object_description" => $this->formatText($object, $since["description"] ?? "")
                            ];
                        }
                    }

                    $object_row["object_todos"] = [];
                    if (!empty($object["todos"])) {
                        foreach ($object["todos"] as $todo) {
                            $object_row["object_todos"][] = [
                                "object_description" => $this->formatText($object, $todo["description"] ?? "")
                            ];
                        }
                    }

                    $object_row["object_links"] = [];
                    if (!empty($object["links"])) {
                        foreach ($object["links"] as $link) {
                            $row = $this->formatLinkTag($object, $link);
                            $object_row["object_links"][] = [
                                "object_url" => $row["url"],
                                "object_tag_link" => $row["tag_link"],
                                "object_description" => $row["description"]
                            ];
                        }
                    }

                    $object_row["object_throws"] = [];
                    if (!empty($object["throws"])) {
                        foreach ($object["throws"] as $throw) {
                            $object_row["object_throws"][] = [
                                "object_item" => $this->formatType('', $throw['doc_type'], $object),
                                "object_description" => $this->formatText($object, $throw["description"] ?? "")
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

    /**
     * Highlights a declaration as PHP without adding line numbers.
     *
     * @param array<string, mixed> $object_descriptor
     * The declaration associated with the displayed statement.
     *
     * @param string $text
     * The native PHP declaration.
     *
     * @return string
     * Returns escaped HTML for the signature.
     *
     * @author Oleg Schildt
     */
    protected function formatPhpStatement(array &$object_descriptor, string $text): string
    {
        return $this->highlighter->highlight($text, fragment: true);
    } // formatPhpStatement

    /**
     * Renders a PHPDoc link tag, using the supplied description as the label for a Markdown reference.
     *
     * @param array<string, mixed> $scope
     * The declaration providing the source directory for relative references.
     *
     * @param array{url: string, description?: \phpDocumentor\Reflection\DocBlock\Description|string|null} $link
     * The destination and optional description parsed from the tag.
     *
     * @return array{url: string, tag_link: string, description: string}
     * Returns the escaped URL, rendered anchor or label, and optional description HTML.
     *
     * @author Oleg Schildt
     */
    protected function formatLinkTag(array $scope, array $link): array
    {
        $url = $link['url'];
        $description = trim((string) ($link['description'] ?? ''));
        $markdown = str_starts_with($url, 'markdown:');
        $label = $markdown ? ($description !== '' ? $description : substr($url, strlen('markdown:'))) : $url;
        $href = $this->resolveMarkdownLink($url, $this->sourceDirectory($scope));
        $label = htmlspecialchars($label);
        $escaped_url = htmlspecialchars($href ?? '');

        return [
            'url' => $escaped_url,
            'tag_link' => $href === null ? $label
                : '<a href="' . $escaped_url . '" target="_blank" rel="noopener">' . $label . '</a>',
            'description' => $markdown ? '' : $this->formatText($scope, $description),
        ];
    } // formatLinkTag

    /**
     * Renders doc-comment Markdown, semantic links, and fenced PHP code.
     *
     * @param array<string, mixed> $scope
     * The declaration providing the source directory, namespace, and imports for inline references.
     *
     * @param string $text
     * The PHPDoc description to render.
     *
     * @return string
     * Returns HTML with escaped source examples and resolved inline links.
     *
     * @author Oleg Schildt
     */
    protected function formatText(array $scope, string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $links = [];
        $literals = [];
        $directory = $this->sourceDirectory($scope);
        $prefix = 'PhpDoxyLink' . hash('sha256', $text);
        $text = preg_replace_callback('/\{@(see|link)\s+([^\s{}]+)(?:\s+([^{}]*))?\}/', function (array $matches) use (
            $scope,
            $directory,
            $prefix,
            &$links,
            &$literals
        ): string {
            $reference = $matches[2];
            $label = trim($matches[3] ?? '') ?: $reference;
            $href = preg_match('~^https?://~i', $reference) ? $reference : null;

            if (str_starts_with($reference, 'markdown:')) {
                $href = $this->resolveMarkdownLink($reference, $directory);
            } elseif ($href === null && $matches[1] === 'see') {
                $target = $this->references->resolve($reference, $scope);
                $href = $target === null ? null : $this->symbolHref($target);
            }

            $key = $prefix . count($links) . 'End';
            $literals[$key] = htmlspecialchars($matches[0]);
            $escaped = htmlspecialchars($label);
            $links[$key] = $href === null ? $escaped
                : '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener">' . $escaped . '</a>';

            return $key;
        }, $text);

        return strtr($this->renderMarkdown($text, $literals, $directory), $links);
    } // formatText

    /**
     * Renders Markdown with the default HTML styling and PHP syntax highlighting.
     *
     * @param string $text
     * The Markdown text, including any leading indentation.
     *
     * @param array<string, string> $literals
     * Escaped literal text to restore inside code blocks and inline code.
     *
     * @param string|null $directory
     * The absolute directory of the source for relative links, if available.
     *
     * @return string
     * Returns safe HTML with highlighted PHP code.
     *
     * @author Oleg Schildt
     */
    protected function renderMarkdown(string $text, array $literals = [], ?string $directory = null): string
    {
        $html = $this->markdown->render($text, fn (string $url): ?string => $this->resolveMarkdownLink($url, $directory));

        return preg_replace_callback('~<code([^>]*)>(.*?)</code>~s', function (array $matches) use ($literals): string {
            $code = strtr($matches[2], $literals);

            if (str_contains($matches[1], 'class="language-php"')) {
                $code = $this->highlighter->highlight(htmlspecialchars_decode($code), fragment: true);
            }

            return '<code' . $matches[1] . '>' . $code . '</code>';
        }, $html);
    } // renderMarkdown

    protected function countNotIgnored(array &$object_list): int
    {
        $cnt = 0;
        foreach ($object_list as &$object_descriptor) {
            if (empty($object_descriptor["ignore"])) {
                $cnt++;
            }
        }

        return $cnt;
    } // countNotIgnored

    /**
     * Builds all pages and publishes the resulting site.
     *
     * @param array<string, mixed> $config
     * Site settings, including source, target, template, title, and optional user files.
     *
     * @return void
     * Replaces the previous site after all files have been written successfully.
     *
     * @throws \RuntimeException
     * If the target is unsafe or template resources or site files cannot be written.
     *
     * @author Oleg Schildt
     */
    public function generate(array &$config): void
    {
        $output = new OutputDirectory($config);
        $this->config = &$config;
        $this->template_dir = rtrim($config['template'], '/\\') . DIRECTORY_SEPARATOR;
        $this->gen_date = date('Y-m-d H:i');
        $this->templates = [];

        $this->indexMarkdownPages();

        echo_highlighted("\n\nHelp files generation started\n");

        $output->publish(function (string $staging_dir): void {
            $this->target_dir = $staging_dir . DIRECTORY_SEPARATOR;
            $this->written_files = [];
            $resources = $this->target_dir . 'resources';

            if (!mkdir($resources)) {
                throw new \RuntimeException("Cannot create resource directory '$resources'.");
            }

            $this->copyDirContents($this->template_dir . 'resources', $resources);

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

            $this->generateMarkdownFiles();
        });

        echo_highlighted("\n\nHelp files generation completed");
    } // generate
} // PhpDocGenerator
