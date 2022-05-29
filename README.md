## PhpDoxy

PhpDoxy is a performant and efficient generator for handful PHP documentation with robust checking for typing errors, missing descriptions,
dead references etc.

### Requirements

- PHP 8.0.x

### Installation

```
composer require smartfactory/phpdoxy"
```

**composer.json**

```
{
  ...

  "require": {
    "php": ">=8.0",
    "smartfactory/phpdoxy": ">=1.0.2"
  }
  
  ...
}
```

### Key features

- Detailed reporting of the object statistics by the processing.
- Detailed reporting of the warning and errors like typing errors, missing descriptions, dead references etc. with referecne to the source file and line.
- Smart resolving of the short object names, based on the context, and linking recognized objects to their documentation. 
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
with your code to be documentated and the target directory for the generated documentation.
3. Run the *generate.php* from your browser.

#### Command Line

The composer creates a "binary" script *vendor/bin/phpdoxy* in the vendor directory. You can call it from command line this:

```
vendor/bin/phpdoxy phpdoxy_config.xml
```

If the configuration file resides in the working directory, it can be omitted by call.

### Configuration

The configuration for the documentation generation is setup in the XML file *phpdoxy_config.xml*.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<config>
    <title>SmartFactory Library</title>

    <source>test_src</source>
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

The name of you project or library. 

**source**

The source directory wuth your code to be documentated. The path can be either absolute or relative. If the path is relative, the PhpDoxy
tries to find the path based from the directory containing the configuration file and from the working directory.

**target**

The target directory for the generated documentation. The path can be either absolute or relative. If the path is relative, the PhpDoxy
tries to find the path based from the directory containing the configuration file and from the working directory.

**template**

The name of the template to be used for the generation. PhpDoxy has the directory *templates* in its base directory. There reside the templates.
Alernatively, you can specify absolute or relative path to the template. If the path is relative, the PhpDoxy
tries to find the path based from the directory containing the configuration file and from the working directory.

**user_files**

This part is optional and should be used if you want to include your own files into the documentation. You should set the following data:

- The title for the contents of your file.
- The short menu title.
- The path to the file with the contents. The path can be either absolute or relative. If the path is relative, the PhpDoxy
- tries to find the path based from the directory containing the configuration file and from the working directory.

### Example

http://php-smart-factory.org/

### Demo

https://github.com/oschildt/PhpDoxyDemo

1. Git-clone the demo application and run 'composer update'.
2. Run *generate.php* from your browser or *generate.cmd/generate.sh*.
3. Study the source code in the directory *src* and the resulting documentation in the directory *docs*.

### Implementation details

The documentation generation is divided in two parts:

- Parsing and creating data model.
- Generating the documentation files based on the data model. 

The *PhpDocParser* parses the source files and creates a *dictionary* (a connected graph) of objects with recursive linking to each other
by reference. Do not try to dump it! It is circular recursive.

This *dictionary* is a pure data model with complete infromation about all objects and their relations, which can be used for the generation
of the documentation.

The *PhpDocGenerator* ueses the *dictionary* and generates the documentation. It is responsible for visual presentation and structuring of the 
documentation.




