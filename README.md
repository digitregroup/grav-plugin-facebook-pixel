# Facebook Pixel Plugin

The **Facebook Pixel** Plugin is for [Grav CMS](http://github.com/getgrav/grav).

## Usage

Before to use it, you should configure the plugin via the admin > plugins panel

### Configuration

You should add the Pixel ID and Access Token for the Facebook Pixel to use for this website.

In order to not pollute real events into the FB Business Events Console, you should go to the "Test mode" tab and
enable (default) the test mode and fill the "Test event name" field with the given test event name (see FB Business Events Console).

#### Rules

Different events can be triggered depending on some rules. Into the "Events rules" tab, you can specify which event is sent and when.

You can specify plain urls or simple regular expressions to be matched :

- `^` = like in PCRE, means "the beginning of the string"
- `$` = like in PCRE, means "the end of the string"
- `*` = the universal matching (like .* in PCRE)
    
Do not escape characters, they are escaped by the plugin

The last rule matching the page URL will apply and trigger the corresponding event.

If you need to trigger the event web a form is sent, append |form at the end of the rule.

Examples:

- `inscription/merci$` will match the following PCRE pattern: `/.*inscription\/merci$/i`
- `*inscription*|form` will match the following PCRE pattern: `/.*inscription.*/i` and be triggered when form is sent.

**Note** The default event is "PageView"
