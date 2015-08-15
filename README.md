# ConvertM1M2

## Background

Magento 2 Merchant Stable is right around the corner, and there's a growing urgency for extension developers to convert 
their existing Magento 1.x extensions to Magento 2.0.

We can't wait until the last minute, because while we don't know how much it will take us to convert, we do know it will 
take long time.

The purpose of this script is to automate as much as possible initial conversion of Magento 1 extension, and allow 
developers to have more time for tasks that can not possibly be automated, such as:
  
  * Templates conversion to new M2 themes
  * JS/CSS conversion to jQuery and new M2 themes
  * Code optimizations and logic improvements
  * etc.
  
## Current status

### Mostly implemented:

  * **Configurations**
    * ACL keys conversion
  * **Layouts**
    * Menu keys conversion
  * **Web files** (copying only, no processing)
  * **Email Templates** (copying only, no processing)
  * **i18n** (copying only)
  * **Templates** (with some conversion - see below)
  * **Classes** (with some conversion - see below)
  * **PHP Code conversions**
    * Class names to backslashed
    * Class declarations to use namespaces
    * Basic class name conversion to new locations in M2
    * String translations to use only `__()`
    * Use ObjectManager instead of Mage::getSingleton() etc.

### To be implemented:

  * **Controllers**
  * **Migration Setup** (not sure if possible to automate at all)
  * **Advanced PHP code conversion**
    * `use` classes
    * Collect used classes and create `__construct` with DI
    * Full list of known class name, ACL keys and menu keys conversions
    
### Partial list of known unknowns:

  * In Magento 1.x it's possible to have separate templates for different themes. If module contains templates for 
  multiple themes, which one to use?
  * In Magento 1.x there are email templates for multiple locales. How is it handled in Magento 2?


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
  
  * **Destination**: `$outputDir` = `../magento2/` - The resulting converted extensions will be stored here, in 
  `magento2/app/code/*`, for quick testing.

When running from CLI, the following parameters are accepted (all optional):

`php ConvertM1M2.php s=source m=mage1_folder o=output`

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



## Disclaimers

This is by no means a complete or even a good solution. 

Some developers opt to start development from scratch and this might be a better choice. 

For those who opt to use this script - there are no guarantees made about any correctness or completeness of the result. 

The purpose of this script is only to reduce some repetitive work which can be automated.

This is work in progress.

## Contributing

All contributions are welcome, especially from Magento2 core developers :)

We want feedback, PRs and success stories!