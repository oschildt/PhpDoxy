## PhpDoxy

PhpDoxy generates PHP API documentation and reports malformed type annotations, missing descriptions, and unresolved references with source locations.

### Example

http://php-smart-factory.org/smartfactory/

### Requirements

- PHP 8.5 or newer for running PhpDoxy
- PHP source syntax through PHP 8.5

### Installation

```
composer require "smartfactory/phpdoxy"
```

**composer.json**

```
{
  ...

  "require": {
    "php": ">=8.5",
    "smartfactory/phpdoxy": ">=2.0.0"
  }
  
  ...
}
```

### Key features

- Declaration counts and diagnostics with source locations.
- Cross-references resolved using each declaration's namespace and imports.
- Native PHP signatures with separate PHPDoc types, including array shapes, generic types, callable signatures, and templates.
- Documentation for enum cases, attributes, promoted properties, property hooks, and asymmetric visibility.
- Ability to include user defined files into the documentation.
- Useful object lookup, displaying results while typing, and direct jump to the found object (no serverside logic necessary, pure JavaScript solution).
- Formatted source code files are also included as documentation. Each object has a reference to the source file and the line. The developer does not need to go
  to the real source files and look for the implementation details, he can directly jump to and study it in the documentation. 
- Summarizing changes, todos and deprecated notes in a special page *Log*.
- It can be run from WEB and command line.
- Customizing look and feel of the documentation through adjusting the template or extending the renderer.

### Supported tags

- @author
- @copyright
- @deprecated 
- @ignore
- @internal
- @license
- @link
- @package
- @param
- @return
- @see
- @since
- @template, @template-covariant, @template-contravariant
- @throws
- @todo
- @uses & @used-by
- @var
- @version

### Usage

The configuration for the PhpDoxy has to be placed to the configuration file *phpdoxy_config.xml*. In the case of command line usage, the
configuration file may have arbitrary name. 

The configuration file should be placed in the working directory of the script. In the case of command line usage, the
configuration file could be passed as parameter with a valid relative or absolute path.  

#### WEB

There is an example script in *examples/generate.php*. 

```php
<?php
namespace PhpDoxy;

ob_implicit_flush();
ob_end_clean();

require_once "vendor/autoload.php";
?><!DOCTYPE html>
<html lang="en">
<head>
    <title>PhpDoxy - Documentation Generation</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
</head>
<body style="background-color:black; margin:0; padding:10px">
<pre style='color:white; margin:0; padding:0'><?php
    generate([]);
    ?>
</pre>
<script type="text/javascript">
    window.scrollTo(0, 10000000);
</script>
</body>
```

1. Put the script to the directory of your application. Ensure that the including of the *vendor/autoload.php* is valid.
2. Put the configuration file *phpdoxy_config.xml* to the directory of your application and specify there the source directory 
with your code to be documented and the target directory for the generated documentation.
3. Run the *generate.php* from your browser.

#### Command Line

The composer creates a "binary" script *vendor/bin/phpdoxy* in the vendor directory. You can call it from command line like this:

```
vendor/bin/phpdoxy phpdoxy_config.xml
```

If the configuration file resides in the working directory, it can be omitted by call.

The command returns status 0 when generation completes, including when it reports documentation warnings. Generation failures return status 1.

### Configuration

The configuration for the documentation generation is setup in the XML file *phpdoxy_config.xml*.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<config>
    <title>SmartFactory Library</title>

    <sources>
        <source>test_src</source>
        <source>examples_src</source>
    </sources>
    <target>test_docs</target>
    <template>default</template>

    <user_files>
        <user_file title="Directory structure - SmartFactory Library" menu_title="Structure">
            add_doc\structure.html
        </user_file>
    </user_files>
</config>
```
**title**

The name of your project or library. 

**sources**

Use one or more `<source>` elements inside `<sources>`. Relative paths are resolved against the configuration directory, then the working directory.
PhpDoxy scans the directories recursively in the listed order and builds a single site. Source pages are labeled by directory when more than one directory is configured.
When separate source directories declare the same symbol, the first declaration supplies the API page and PhpDoxy reports a warning. The source pages are generated for both files.
Markdown files in the source directories become standalone pages titled after their filename. They share the site's styling, including PHP syntax highlighting, and are accessible by direct links. Relative links between discovered Markdown files are converted to the generated page URLs.

Links in Markdown pages and PHPDoc descriptions open in a new tab. Use the source filename to link to a generated Markdown page.

```php
/**
 * See the [Redis throttling guide](markdown:RedisRequestThrottlingManager.md)
 * for setup and usage.
 *
 * @link markdown:RedisRequestThrottlingManager.md Redis throttling guide
 *
 * @author Oleg Schildt
 */
```

The `markdown:` syntax also works in standalone Markdown pages. PhpDoxy first resolves the name or relative path from the file containing the link. A bare filename can also match a unique Markdown file across the configured sources. If several files share the name, specify a relative path such as `markdown:../guides/setup.md`.

**target**

The dedicated output directory. Its parent must exist. Relative paths are resolved against the configuration directory, then the working directory.
Use an empty directory, a new path, or an existing PhpDoxy site. PhpDoxy builds the new site next to the target and replaces the previous site after a successful build.

**template**

The template name or a path to a template directory. Relative paths are resolved against the configuration directory, the working directory, then PhpDoxy's bundled *templates* directory.
The default class, interface, and trait templates include the shared *members.tpl* partial.

**user_files**

This part is optional and should be used if you want to include your own files into the documentation. You should set the following data:

- The title for the contents of your file.
- The short menu title.
- The path to the file with the contents. The path can be either absolute or relative. If the path is relative, the PhpDoxy tries to 
  find the path based from the directory containing the configuration file and from the working directory.

### Demo

https://github.com/oschildt/PhpDoxyDemo

1. Git-clone the demo application and run 'composer update'.
2. Run *generate.php* from your browser or *generate.cmd/generate.sh*.
3. Study the source code in the directory *src*, *instructions*, *additional* and the resulting documentation in the directory *docs*.

### Implementation details

*PhpDocParser* parses PHP declarations with PHP-Parser and type annotations with PHPStan's PHPDoc Parser. The documentation dictionary holds declaration, namespace, and member relationships by reference and may contain cycles.

*PhpDocGenerator* renders that dictionary into HTML. It uses PHP's tokenizer to highlight declarations, PHP examples, and source pages with physical line anchors. PHPStan can check the semantic compatibility of PHPDoc types with PHP signatures and code.

