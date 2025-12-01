Mapper (module for Omeka S)
===========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Mapper] is a module for [Omeka S] that allows to define mapping between a
source string or record and a destination value or resource.

Default mappings are available for Unimarc, EAD, and Mets.

This module is used in modules:
- [Advanced Resource Template] to define autofillers,
- [CopIdRef] to create local resource from French authorities [IdRef],
- [Bulk Import] to convert any source (spreadsheet, sql, xml, etc.) into omeka
  resource.

And many more.


Installation
------------

See general end user documentation for [installing a module].

This module requires the module [Common], that should be installed first.

The module uses external libraries, so use the release zip to install it, or use
and init the source.

- From the zip

Download the last release [Mapper.zip] from the list of releases, and
uncompress it in the `modules` directory.

- From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Mapper`, go to the root of the module, and run:

```sh
composer install --no-dev
```

- For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/Mapper/test/phpunit.xml --testdox
```


Usage
-----

Copy and edit the configuration as you need.


TODO
----

- [ ] See [Advanced Resource Template].
- [ ] See [Bulk Import].
- [ ] See [CopIdRef].
- [ ] Replace key or name "twig" by "filter" everywhere.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.

```sh
# database dump example
mysqldump -u omeka -p omeka | gzip > "omeka.$(date +%Y%m%d_%H%M%S).sql.gz"
```


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

- Copyright Daniel Berthereau, 2012-2025 (see [Daniel-KM] on GitLab)

This module is a merge and improvement of previous modules [Advanced Resource Template],
[CopIdRef], [Bulk Import] and various old scripts.


The merge of modules was implemented for the module [Urify] designed for the
[digital library Manioc] of the [Université des Antilles et de la Guyane].

[Mapper]: https://gitlab.com/Daniel-KM/Omeka-S-module-Mapper
[Omeka S]: https://omeka.org/s
[Advanced Resource Template]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedResourceTemplate
[CopIdRef]: https://gitlab.com/Daniel-KM/Omeka-S-module-CopIdRef
[Bulk Import]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkImport
[Urify]: https://gitlab.com/Daniel-KM/Omeka-S-module-Urify
[installing a module]: https://omeka.org/s/docs/user-manual/modules/
[Mapper.zip]: https://github.com/Daniel-KM/Omeka-S-module-Mapper/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Mapper/issues
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[digital library Manioc]: http://www.manioc.org
[Université des Antilles et de la Guyane]: http://www.univ-ag.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
