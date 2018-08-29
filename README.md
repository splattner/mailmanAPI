# mailmanAPI
A Simple PHP API to work with Mailman 2.x Mailinglists

As Mailman 2.x seems not to offer a proper API, this Mailman API provides some basic functionality to work with Mailman.
Be aware, the library only wrappes around the HTML Forms of the Mailman API Site. It parses the HTTP Responses & HTML Pages, for Authentication Cookies, CSRF TOKEN and then posts to the FORM action url.

Testes with Mailman 2.1.20, no guarantee to work with other versions.


## Features
- Get all Members of a Maillist
- Add Members to a Maillist
- Remove Members from a Maillist
- Change Address of a Member

## Requirements
- Socket enabled or curl extension installed
- PHP 5.3+

## Installation
```
composer require splattner/mailmanapi:^1.2
```

## Usage

You need the URL for your Mailman Mailist e.g. http://{{domain}}/mailman/admin/{{maillistName}} and your Administration Password for the Maillist.

* [Get All Members](#get-all-members)
* [Add Members](#add-members)
* [Remove Members](#remove-members)
* [Change Members](#change-member)


### Get All Members

```
$mailman = new MailmanAPI($mailManBaseURL,$adminPW);
$allMembers = $mailman->getMemberlist();
```

### Add Members

```
$mailman = new MailmanAPI($mailManBaseURL,$adminPW);
$mailman->addMembers(["member1@domain.com","member2@domain.com"]);
```

### Remove Members

```
$mailman = new MailmanAPI($mailManBaseURL,$adminPW);
$mailman->removeMember(["member1@domain.com","member2@domain.com"]);
```

### Change Member

```
$mailman = new MailmanAPI($mailManBaseURL,$adminPW);
$mailman->changeMember("memberold@domain.com","membernew@domain.com");
```
