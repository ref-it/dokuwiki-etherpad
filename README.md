# Etherpad Lite Plugin for DokuWiki

This DokuWiki plugin lets you edit your pages using an existing etherpad lite instance. Using an appropiate configuration of the etherpad lite server, the plugin will enforce DokuWiki acl permissions for pages onto the pads for editing the page.

## Benefits

  * multiple persons can edit the same page at the same time
  * almost realtime backups of edits typed
  * tight toolbar integration
  * mapping of DokuWiki permissions
  * extra password protection for read/readwrite pad access

## Usage

The user who is in (DokuWiki) "edit" mode can create a pad to edit and save the content. Users in "lock" mode can join this pad, but not save nor delete the pad.

## How does it work?

The DokuWiki plugin adds javascript code to the edit page that hooks into the toolbar javascript and adds an extra pad-toogle icon just below the textedit field. This code obviously depends on the template used and has *not* been tested with the most recent DokuWiki default template. The DokuWiki plugin further adds an ajax handler that calls the etherpad lite api as needed and stores the pad details in the DokuWiki page metadata object.

The etherpad lite gets its pads assigned to groups, group membership managed and pad passwords assigned by the DokuWiki plugin. Further, the DokuWiki plugin sets browser cookies to authorize the client to use the pad. The latter leads to some cross-domain requirements, though this could as well be fixed by adding extra code to etherpad lite.

The tight integration works using javascript cross-domain message posting, so it is more or less cross-domain independend. The DokuWiki plugin sends edit-messages (i.e. past text xx at current cursor) and the etherpad lite plugin receives and processes it. Therefore the etherpad lite plugin is just some javascript code loaded into the browser. Messages in the inverse directions are used to indicate the presence of the plugin. Please note that there currently is no synchronous messaging possible, so the DokuWiki javascript code cannot read the current selection from the pad.

## Installation

### Etherpad Lite

Please refer to the etherpad lite dokumentation for its installation steps and remember to use a production-ready backend.

To ensure pad permissions and cleanup, the following etherpad lite settings are recommended. They ensure that only users authorized by the DokuWiki plugin can edit a pad and that there are only pads created using the DokuWiki plugin.

```
"requireSession" : true,
"editOnly" : true,
```

### This Plugin

Please refer to the [DokuWiki Documentation](https://www.dokuwiki.org/plugin_installation_instructions) for additional info on how to install plugins in DokuWiki.

#### Manual Installation

Use the following command to install the plugin into DokuWiki. The path name (etherpadlite in the lib/plugins folder) is important - a different name will not work!

```
git clone https://github.com/ref-it/DokuWiki-etherpadlite lib/plugins/etherpadlite
```

## Configuration

This plugin needs configuration. See the DokuWiki configuration editor for this. More information can be found on https://www.dokuwiki.org/plugin:etherpadlite .


## Shortcomings

  * The DokuWiki plugin sets a browser cookie read by the etherpad lite (session identifier). This leads to some cross-domain restrictions.
  * Group sessions last for one week or shorter (if user uses logout button). So after one week, you'll need to reconnect.
  * Pads are owned by the user who created it. Ownership cannot be transfered. If a pad exists for a page revision, there cannot be another pad for the same/a different page revision.
  * the DokuWiki integration depends on the template used and is only tested with the current [DokuWiki default template](https://www.dokuwiki.org/template:DokuWiki).
  
---

## License

Copyright (C) Michael Braun <michael-dev@fami-braun.de>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

## Used Software and Artwork

**Etherpad Lite Client Library**\
© 2011 jwalck (https://github.com/jwalck) \
© 2011-2012 Tom Hudson (https://github.com/tomnomnom) \
[Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0)

**Material Design Icons**\
© The Pictogrammers\
[Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0)
