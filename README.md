# ILIAS Plugin OnlyOffice

This plugin offers a way to connect an OnlyOffice document server to ILIAS. Users can upload files by creating an ILIAS repository object, which then can be collaboratively edited in OnlyOffice's online editors.

## Requirements

| Component | Version(s)                                                                                    | Link                      |
|-----------|-----------------------------------------------------------------------------------------------|---------------------------|
| PHP       | ![](https://img.shields.io/badge/8.3-blue.svg) ![](https://img.shields.io/badge/8.4-blue.svg) | [PHP](https://php.net)    |
| ILIAS     | ![](https://img.shields.io/badge/11-orange.svg)                                               | [ILIAS](https://ilias.de) |

---

<!-- TOC -->
* [ILIAS Plugin OnlyOffice](#ilias-plugin-onlyoffice)
    * [Requirements](#requirements)
    * [Installation](#installation)
        * [Migration](#migration)
            * [Files to IRSS migration](#files-to-irss-migration)
            * [File templates to IRSS migration](#file-templates-to-irss-migration)
        * [OnlyOffice docker container](#onlyoffice-docker-container)
    * [Lucene Indexing](#lucene-indexing)
    * [Troubleshooting](#troubleshooting)
    * [License](#license)
<!-- TOC -->

---


## Installation

1. Clone this repository to **public/Customizing/global/plugins/Services/Repository/RepositoryObject/OnlyOffice**
2. Install the Composer dependencies
   ```bash
   cd public/Customizing/global/plugins/Services/Repository/RepositoryObject/OnlyOffice
   composer install --no-dev
   ```
   Developers **MUST** omit the `--no-dev` argument.
3. Run ``composer install --no-dev`` in the ilias root directory!
4. Login to ILIAS with an administrator account (e.g. root)
5. Select **Plugins** in **Extending ILIAS** inside the **Administration** main menu.
6. Search for the **OnlyOffice** plugin in the list of plugin and choose **Install** from the **Actions**
   drop-down.
7. Choose **Activate** from the **Actions** dropdown.

### Migration

If updating from an existing installation migrations are required to move files from the web directory to the ILIAS Resource Storage (IRSS).

#### Files to IRSS migration

This migration will iterate over all existing db entries and attempt to move files to the IRSS and store the resource identification in a new db column **rid**.  
Files are deleted from the original web dir after successful migration.

```shell
cd /var/www/<ilias installation path>
php8.3 cli/setup.php migrate --run OnlyOffice.FileIrssMigration -y
```

#### File templates to IRSS migration

This migration will iterate over all existing file template files in the web dir and move them to the IRSS.   
Their resource identification is then stored in the new **xono_file_template** db table.   
Files are deleted from the original web dir after successful migration

```shell
cd /var/www/<ilias installation path>
php8.3 cli/setup.php migrate --run OnlyOffice.FileTemplateIrssMigration -y
```

### OnlyOffice docker container

See [docker-compose-ee.example.yml](doc/docker-compose-ee.example.yml) for an example.


Install the desired edition of OnlyOffice Docs on your server.
Note that the free community edition allows only 20 simultaneous connections.
Installation Guides can be found [here](https://helpcenter.onlyoffice.com/installation/docs-index.aspx)

Use `http://%onlyoffice_ip%` as `ONLYOFFICE root URL` in plugin configuration

## Lucene Indexing

This plugin supports lucene indexing of the basic object data.

## Troubleshooting
1. If the OnlyOffice window opens up but you can't access the file make sure that your webserver user has read and write access to the plugin folder and the data folder where the files are saved.

## License

This project is licensed under the GPL v3 License
