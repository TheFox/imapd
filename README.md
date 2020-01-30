# IMAPd

IMAP server (library) to serve emails to an email client, written in pure PHP.

The `d` in `SMTPd` stands for [Daemon](https://en.wikipedia.org/wiki/Daemon_(computing)). This script can run in background like any other daemon process. It's not meant for running as a webapplication.

## Why this project?

Believe it or not, **email is still the killer feature of the Internet**. There are tons of projects for accessing and fetching emails from an IMAP/POP3 server. But there are not so many providing a programmatically interface to serve emails to an email client.

With this interface you can do something like this for your app users:

```
+--------------+     +-------+     +------------------------+     +------+
| Your PHP App +---> | IMAPd +---> | MUA (like Thunderbird) +---> | User |
+--------------+     +-------+     +------------------------+     +------+
```

This is useful when you have a messaging application written in PHP but no graphical user interface for it. So your graphical user interface can be any [email client](http://en.wikipedia.org/wiki/Email_client). [Thunderbird](https://www.mozilla.org/en-US/thunderbird/) for instance.

## Project Outlines

The project outlines as described in my blog post about [Open Source Software Collaboration](https://blog.fox21.at/2019/02/21/open-source-software-collaboration.html).

- The main purpose of this software is to provide a server-side IMAP API for PHP scripts.
- Although the RFC implementations are not completed yet, they must be strict.
- More features can be possible in the future. In perspective of the protocols the features must be a RFC implementation.
- This list is open. Feel free to request features.

## Planned Features

- Full RFC 3501 Implementation.
- Replace `Zend\Mail` with a better solution.

## Installation

The preferred method of installation is via [Packagist](https://packagist.org/packages/thefox/imapd) and [Composer](https://getcomposer.org/). Run the following command to install the package and add it as a requirement to composer.json:

```bash
composer require thefox/imapd
```

## Usage

See [`example.php`](example.php) file for more information.

## RFC 3501 Implementation

### Complete implementation

- 6.1.2 NOOP Command
- 6.1.3 LOGOUT Command
- 6.4.1 CHECK Command
- 6.4.7 COPY Command
- 7.1.1 OK Response
- 7.1.2 NO Response
- 7.1.3 BAD Response
- 7.1.5 BYE Response
- 7.4.1 EXPUNGE Response

### Incomplete implemention

- 2.3.1.1 Unique Identifier (UID) Message Attribute
- 2.3.1.2 Message Sequence Number Message Attribute
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
- 6.4.4 SEARCH Command
- 6.4.5 FETCH Command
- 6.4.6 STORE Command
- 6.4.8 UID Command
- 7.1.4 PREAUTH Response
- 7.2.1 CAPABILITY Response
- 7.2.2 LIST Response
- 7.2.3 LSUB Response
- 7.2.5 SEARCH Response
- 7.3.1 EXISTS Response
- 7.3.2 RECENT Response
- 7.4.2 FETCH Response

## TODO

- Some tasks are commented with `NOT_IMPLEMENTED`. Implement these.
- `@TODO` are to be complete the PHP Code Sniffer tests before releasing a new version.

## Alternatives for `Zend\Mail`

- [exorus/php-mime-mail-parser](https://packagist.org/packages/exorus/php-mime-mail-parser) (requires ext-mailparse PHP extension)

## Related Links

- [RFC 3501](https://tools.ietf.org/html/rfc3501)
- [Email Will Last Forever](http://blog.frontapp.com/email-will-last-forever/)
- [Email Is Still the Best Thing on the Internet](http://www.theatlantic.com/technology/archive/2014/08/why-email-will-never-die/375973/)
- [Believe it or not, email is still the killer app](http://www.digitaltrends.com/mobile/believe-it-or-not-email-is-still-the-killer-app/#!bs4oTU)
- [Developers: stop re-AOLizing the web!](http://technicalfault.net/2014/07/03/developers-stop-re-aolizing-the-web/)
- [Set up your own email server in 5 steps](https://forum.bytemark.co.uk/t/set-up-your-own-email-server-in-5-steps/1864)

## Related Projects

- [SMTPd](https://github.com/TheFox/smtpd)

## Project Links

- [Blog Post about IMAPd](http://blog.fox21.at/2014/08/07/imapd.html)
- [Packagist Package](https://packagist.org/packages/thefox/imapd)
- [Travis CI Repository](https://travis-ci.org/TheFox/imapd)
- [PHPWeekly - Issue August 7, 2014](http://phpweekly.com/archive/2014-08-07.html)
