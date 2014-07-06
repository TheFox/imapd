# IMAPd
IMAP server (library) written in PHP.

## Installation
The preferred method of installation is via [Packagist](https://packagist.org/packages/thefox/imapd) and [Composer](https://getcomposer.org/). Run the following command to install the package and add it as a requirement to composer.json:

`composer.phar require "thefox/imapd=0.1.*"`

## Stand-alone server
To start a stand-alone server you can type the following command in your shell:

`./application.php server -d`

To show the usage options use `-h`:

`./application.php server -h`

You can change the IP and port (default port is 20143):

`./application.php server -a 0.0.0.0 -p 143`

## RFC 3501 Implementation
### Complete
- 6.1.2 NOOP Command
- 6.1.3 LOGOUT Command
- 6.4.1 CHECK Command
- 6.4.7 COPY Command
- 7.1.1 OK Response
- 7.1.2 NO Response
- 7.1.3 BAD Response
- 7.1.5 BYE Response
- 7.4.1 EXPUNGE Response

### Incomplete
- 2.3.2 Flags Message Attribute
- 6.1.1 CAPABILITY Command
- 6.2.2 AUTHENTICATE Command
- 6.2.3 LOGIN Command
- 6.3.1 SELECT Command
- 6.3.6 SUBSCRIBE Command
- 6.3.7 UNSUBSCRIBE Command
- 6.3.8 LIST Command
- 6.3.9 LSUB Command
- 6.3.11 APPEND Command
- 6.4.2 CLOSE Command
- 6.4.5 FETCH Command
- 6.4.6 STORE Command
- 6.4.8 UID Command
- 7.1.4 PREAUTH Response
- 7.2.1 CAPABILITY Response
- 7.2.2 LIST Response
- 7.2.3 LSUB Response
- 7.3.1 EXISTS Response
- 7.3.2 RECENT Response
- 7.4.2 FETCH Response

## Contribute
You're welcome to contribute to this project. Fork this project at <https://github.com/TheFox/imapd>. You should read GitHub's [How to Fork a Repo](https://help.github.com/articles/fork-a-repo).

## License
Copyright (C) 2014 Christian Mayer <http://fox21.at>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
