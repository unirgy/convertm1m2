# ConvertM1M2

## Background

The purpose of this script is to automate as much as possible the initial conversion of a Magento 1 extension, and allow 
developers to have more time for tasks that can not be automated, such as:
  
  * Templates conversion to new M2 themes
  * JS/CSS conversion to jQuery and new M2 themes
  * Code optimizations and logic improvements
  * etc.

> NOTE: this script will not produce fully working code. A developer will have to manually go over each resulting file and test/fix it by hand.
  
## Current status

### Implemented:

  * **Configurations**
    * ACL keys conversion
  * **Layouts**
    * Menu keys conversion
    * Block class names conversion
    * Template namespaces
  * **Web files** (copying to correct folder only, no processing)
  * **Email Templates** (copying to correct folder only, no processing)
  * **i18n** (copying to correct folder only)
  * **Templates** (with some conversion - see below)
  * **Classes** (with some conversion - see below)
  * **Controllers** (separate files per action)
  * **Observers** (separate files per observer callback)
  * **PHP Code conversions**
    * Class names to backslashed
    * Class declarations to use namespaces
    * Basic class name conversion to new locations in M2
    * String translations to use only `__()`
    * Use constructor Dependency Injection
    * `use` classes
    * Collect correct DI construct arguments from parent classes

### Potentially to be implemented:

  * **Migration Setup** (not sure if possible to automate at all)
  * **Advanced PHP code conversion**
    * Full list of known class name, ACL keys and menu keys conversions
    
### Partial list of known unknowns:

  * In Magento 1.x it's possible to have separate templates for different themes. If a module contains templates for 
  multiple themes, which one should be used?
  * In Magento 1.x there are email templates for multiple locales. How this handled in Magento 2?


## Installation and Usage

The script is fully standalone and self-contained, with the exception of SimpleDOM.php library, which is included in the 
package.

Fork/clone the repository, and edit ConvertM1M2.php file configuration (at the beginning of the file) if/as necessary.

The script can be ran from the Web or CLI, and allows conversion of multiple extensions at the same time.

When running from the web, no parameters can be accepted, and the script expects these locations:

  * **Magento 1**: `$mage1Dir` = `../magento/` - this is required for fetching configurations and layouts. It should 
  contain all core Magento modules, all the modules to be converted, and all their dependencies.
  
  * **Source Modules**: `$sourceDir` = `source/` - Copy here your extensions to be converted, files for each extension 
  in a separate folder, named the same as extensions.
  
  * **Destination**: `$mage2Dir` = `../magento2/` - The resulting converted extensions will be stored here, in 
  `magento2/app/code/*`, for quick testing.

When running from CLI, the following parameters are accepted (all optional):

`php ConvertM1M2.php s=source m=mage1_folder o=output a=stage`

An example of file and folders structure:


    [] Web or CLI root
    |
    +-[] convertm1m2/
    | +-() ConvertM1M2.php   - execute this script
    | +-[] source/
    |   +-[] Vendor_Module1/ - here are all the original files of your Magento1 extension, with full folder structure
    |   +-[] Vendor_Module2/
    |     +-[] app/code/community/Vendor/Module2/...
    |     +-[] app/etc/modules/Vendor_Module2.xml
    |     +-[] skin/frontend/base/default/...
    |
    +-[] magento/           - Magento 1 root folder with all core code, extensions to be converted, and dependencies
    | +-[] app/
    | +-[] skin/
    | +-[] ...
    |
    +-[] magento2/          - Magento 2 root folder
    | +-[] app/
    |   +-[] code/
    |     +-[] Vendor/
    |       +-[] Module1/   - here are the resulting output files of the converted extension
    |       +-[] Module2/


## Steps after automatic conversion

  * Run stage 2 to check that classes can be loaded by php without errors
    * `http://127.0.0.1/convertm1m2/ConvertM1M2.php?a=2` OR
    * `$ php ConvertM1M2.php a=2`
  * Fix any parent or interface classes as these could have been changed
  * Fix any constructors or other methods arguments as they might have been changed
  * Go over all the output files and try to understand what they mean and how they map to respective functionality in M1 code.
  * Manually convert CSS, JS and templates to support M2 themes.
  * Test your extensions.
  * Learn more about M2 and use the knowledge to improve your code and gain experience.

## Disclaimers

Our conversion tool is a work in progress and may not necessarily be the best solution for converting your existing extension.

Some developers may opt to start development from scratch and this might be a better choice. 

For those who do opt to use this script - there are no guarantees made about any correctness or completeness of the result. 

The purpose of this script is only to reduce some repetitive work which can be automated.

## Contributing

All contributions are welcome, especially from Magento2 core developers :)

We want feedback, PRs and success stories!
