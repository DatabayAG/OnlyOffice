# ILIAS Plugin OnlyOffice

This plugin offers a way to connect an OnlyOffice document server to ILIAS. Users can upload files by creating an ILIAS repository object, which then can be collaboratively edited in OnlyOffice's online editors.

## Requirements

| Component | Version(s)                                                                                    | Link                      |
|-----------|-----------------------------------------------------------------------------------------------|---------------------------|
| PHP       | ![](https://img.shields.io/badge/8.1-blue.svg) ![](https://img.shields.io/badge/8.2-blue.svg) | [PHP](https://php.net)    |
| ILIAS     | ![](https://img.shields.io/badge/9.x-orange.svg)                                              | [ILIAS](https://ilias.de) |

---

<!-- TOC -->
* [ILIAS Plugin OnlyOffice](#ilias-plugin-onlyoffice)
  * [Requirements](#requirements)
  * [Installation](#installation)
    * [OnlyOffice docker container](#onlyoffice-docker-container)
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

### OnlyOffice docker container

See [docker-compose-ee.example.yml](doc/docker-compose-ee.example.yml) for an example.


Install the desired edition of OnlyOffice Docs on your server.
Note that the free community edition allows only 20 simultaneous connections.
Installation Guides can be found [here](https://helpcenter.onlyoffice.com/installation/docs-index.aspx)

Use `http://%onlyoffice_ip%` as `ONLYOFFICE root URL` in plugin configuration

## Troubleshooting
1. If the OnlyOffice window opens up but you can't access the file make sure that your webserver user has read and write access to the plugin folder and the data folder where the files are saved.

## License

This project is licensed under the GPL v3 License
