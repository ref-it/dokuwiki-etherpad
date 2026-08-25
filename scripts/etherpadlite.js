var ep = { };

ep.config = etherpad_lite_config;
ep.aceWasEnabled = false;
ep.cmWasEnabled = false;
ep.isOwner = false;
ep.readOnly = false;
ep.isSaveable = false;
ep.timer = null;
ep.lang = null;
ep.password = "";
ep.opened = false;
ep.hasPadPlugin = false;

/* icons: inline Lucide SVGs (see images/icons/*.svg for the vendored
   originals and license). Inlined directly in the DOM - rather than
   loaded as external <img>/mask files - so their stroke="currentColor"
   picks up the surrounding text color via normal CSS inheritance and
   stays visible in both light and dark themes. */
ep.icons = {
  "pencil": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>',
  "pencil-off": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10 10-6.157 6.162a2 2 0 0 0-.5.833l-1.322 4.36a.5.5 0 0 0 .622.624l4.358-1.323a2 2 0 0 0 .83-.5L14 13.982"/><path d="m12.829 7.172 4.359-4.346a1 1 0 1 1 3.986 3.986l-4.353 4.353"/><path d="m15 5 4 4"/><path d="m2 2 20 20"/></svg>',
  "circle-x": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
  "lock": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  "lock-open": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>',
  "save": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>',
  "save-off": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 13H8a1 1 0 0 0-1 1v7"/><path d="M14 8h1"/><path d="M17 21v-4"/><path d="m2 2 20 20"/><path d="M20.41 20.41A2 2 0 0 1 19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 .59-1.41"/><path d="M9 3h6.2a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V15"/></svg>'
};

ep.setIcon = function($el, name) {
  $el.html(ep.icons[name]);
  return $el;
};

ep.makeIcon = function(classes, name) {
  return ep.setIcon(jQuery("<span/>").addClass(classes), name);
};

ep.on_disable = function() {
  console.log('1');
  if (ep.isOwner) {
  jQuery.post(
      DOKU_BASE + 'lib/exe/ajax.php',
      { 'id' : ep.config["id"], "rev" : ep.config["rev"], "call" : "pad_getText", "isSaveable" : ep.isSaveable, "readOnly"   : false },
      function(data) {
          if (data.error) {
             alert(data.error);
          } else {
             jQuery('#wiki__text').val(data.text);
             self.textChanged = true;
             jQuery('.pad-toggle').hide();
             jQuery('.pad-toggle-off').show();
             jQuery('.pad-iframecontainer').html("");
             jQuery('.pad-iframecontainer').hide();
             jQuery('#wiki__text').show();
             jQuery(".pad-action-buttons").hide();
             jQuery(".nopad-action-buttons").show();
             jQuery('.ace-toggle-hidden').removeClass('ace-toggle-hidden').show();
             if (!ep.isSaveable) { // fix toolbar
               jQuery('#wiki__text').attr('readOnly','readOnly');
               jQery('tool__bar').empty();
             }
             ep.opened = false;
             ep.on_disable_close();
          }
      }
    );
  } else {
     jQuery('.pad-toggle').hide();
     jQuery('.pad-toggle-off').show();
     jQuery('.pad-iframecontainer').html("");
     jQuery('.pad-iframecontainer').hide();
     jQuery('#wiki__text').show();
     jQuery(".pad-action-buttons").hide();
     jQuery(".nopad-action-buttons").show();
     jQuery('.ace-toggle-hidden').removeClass('ace-toggle-hidden').show();
     if (!ep.isSaveable) { // fix toolbar
       jQuery('#wiki__text').attr('readOnly','readOnly');
       jQuery('#tool__bar').empty();
     }
     ep.opened = false;
     if (ep.aceWasEnabled) {
        ep.aceShow();
     }
     if (ep.cmWasEnabled) {
        ep.cmShow();
     }
  }
};

ep.on_disable_close = function() {
  window.clearInterval(ep.timer); ep.timer = null;
  jQuery.post(
    DOKU_BASE + 'lib/exe/ajax.php',
    { "id"         : ep.config["id"], "rev" : ep.config["rev"], "call" : "pad_close",
      "sectok"     : jQuery('input[name=sectok]').val(),
      "prefix"     : jQuery('#dw__editform').find('input[name=prefix]').val(),
      "suffix"     : jQuery('#dw__editform').find('input[name=suffix]').val(),
      "date"       : jQuery('#dw__editform').find('input[name=date]').val(),
      "isSaveable" : ep.isSaveable,
      "readOnly"   : false
    },
    function(data) {
        if (data.error) {
           alert(data.error);
        } else {
           jQuery('#wiki__text').val(data.text);
           self.textChanged = true;
           if (ep.aceWasEnabled) {
              ep.aceShow();
           }
           if (ep.cmWasEnabled) {
              ep.cmShow();
           }
        }
    }
  );
};

ep.on_password_cancel = function(event) {
  ep.pwdlg.dlg.dialog('close');
  return false;
}

ep.on_password_submit = function() {
  ep.password = ep.pwdlg.inp.val();
  ep.pwdlg.dlg.dialog('close');
  ep.on_re_enable(true);
  return false;
}
ep.on_password_click = function() {
  if (!ep.readOnly) {
    alert(ep.lang.alreadywriteable);
  } else {
    ep.on_password();
  }
  return false;
}

ep.on_password = function() {
    ep.pwdlg.inp.val(ep.password);
    ep.pwdlg.dlg.dialog('open');
}

ep.init_password = function() {
  ep.pwdlg = {};
  ep.pwdlg.dlg = jQuery('<div/>').attr('title',ep.lang.password);
  ep.pwdlg.frm = jQuery('<form/>').addClass('pad-form').submit(ep.on_password_submit).appendTo(ep.pwdlg.dlg);
  jQuery('<label/>').attr('for','password').text(ep.lang.passwordforpad).appendTo(ep.pwdlg.frm);
  ep.pwdlg.inp = jQuery('<input/>').attr('name','password').attr('type','password').appendTo(ep.pwdlg.frm);
  jQuery('<input/>').attr('type','submit').val(ep.lang.submit).click(ep.on_password_submit).appendTo(ep.pwdlg.frm);
  jQuery('<input/>').attr('type','reset').val(ep.lang.reset).click(ep.on_password_cancel).appendTo(ep.pwdlg.frm);
  ep.pwdlg.dlg.dialog({modal: true, width: 500,height:150, autoOpen: false});
}

ep.init_security = function() {
  ep.dlg = {};
  ep.dlg.dlg = jQuery('<div/>').attr('title',ep.lang.securitymanager);
  ep.dlg.frm = jQuery('<form/>').addClass('pad-form').submit(ep.on_security_submit).appendTo(ep.dlg.dlg);
  var encALabel = jQuery('<label/>').attr('for','encAccessMode').text(ep.lang.accessRequires+':');
  ep.dlg.encAMode = jQuery('<select/>').attr('name','encAccessMode').attr('size',1);
   ep.dlg.encAMode.append(jQuery('<option/>').val('wikiread').text(ep.lang.permToReadWiki));
   ep.dlg.encAMode.append(jQuery('<option/>').val('wikiwrite').text(ep.lang.permToWriteWiki));
  
  var readLabel = jQuery('<label/>').attr('for','readMode').text(ep.lang.readAccessRequires+':');
  ep.dlg.readMode = jQuery('<select/>').attr('name','readMode').attr('size',1).change(ep.on_security_readmode_changed);
   ep.dlg.readMode.append(jQuery('<option/>').val('wikiread').text(ep.lang.permToReadWiki));
   ep.dlg.readMode.append(jQuery('<option/>').val('wikiread+password').text(ep.lang.permToReadWikiPlusPassword));
   ep.dlg.readMode.append(jQuery('<option/>').val('wikiwrite').text(ep.lang.permToWriteWiki));
   ep.dlg.readMode.append(jQuery('<option/>').val('wikiwrite+password').text(ep.lang.permToWriteWikiPlusPassword));
  ep.dlg.readPassword = jQuery('<span/>').hide();
  jQuery('<label/>').attr('for','readpw').text(ep.lang.readPassword+':').appendTo(ep.dlg.readPassword);
  ep.dlg.readPasswordFrm = jQuery('<input/>').attr('name','readpw').attr('type','password').appendTo(ep.dlg.readPassword);

  var writeLabel = jQuery('<label/>').attr('for','writeMode').text(ep.lang.writeAccessRequires+':');
  ep.dlg.writeMode = jQuery('<select/>').attr('name','writeMode').attr('size',1).change(ep.on_security_writemode_changed);
   ep.dlg.writeMode.append(jQuery('<option/>').val('wikiwrite').text(ep.lang.permToWriteWiki));
   ep.dlg.writeMode.append(jQuery('<option/>').val('wikiwrite+password').text(ep.lang.permToWriteWikiPlusPassword));
  ep.dlg.writePassword = jQuery('<span/>').hide();
  jQuery('<label/>').attr('for','writepw').text(ep.lang.writePassword+':').appendTo(ep.dlg.writePassword);
  ep.dlg.writePasswordFrm = jQuery('<input/>').attr('name','writepw').attr('type','password').appendTo(ep.dlg.writePassword);

  ep.dlg.noEnc = jQuery('<span/>').show();
  ep.dlg.noEnc.append(readLabel).append(ep.dlg.readMode).append(ep.dlg.readPassword);
  ep.dlg.noEnc.append(writeLabel).append(ep.dlg.writeMode).append(ep.dlg.writePassword);
  ep.dlg.frm.append(ep.dlg.noEnc);

  jQuery('<input/>').attr('type','submit').val(ep.lang.submit).click(ep.on_security_submit).appendTo(ep.dlg.frm);
  jQuery('<input/>').attr('type','reset').val(ep.lang.reset).click(ep.on_security_cancel).appendTo(ep.dlg.frm);

  ep.dlg.encAMode.val('wikiwrite');
  ep.dlg.readMode.val('wikiwrite');
  ep.dlg.writeMode.val('wikiwrite');
  ep.dlg.readPasswordFrm.val('');
  ep.dlg.writePasswordFrm.val('');

  ep.dlg.dlg.dialog({modal: true, width: 600,height:300, autoOpen: false});
}

ep.on_security = function() {
  ep.dlg.dlg.dialog('open');
  return false;
}

ep.on_security_submit = function() {
  jQuery.post(
    DOKU_BASE + 'lib/exe/ajax.php',
    { 'id' : ep.config["id"], "rev" : ep.config["rev"], "call" : "pad_security",
      "sectok"     : jQuery('input[name=sectok]').val(),
      "encAMode"   : ep.dlg.encAMode.val(),
      "readMode"   : ep.dlg.readMode.val(),
      "writeMode"  : ep.dlg.writeMode.val(),
      "readpw"     : ep.dlg.readPasswordFrm.val(),
      "writepw"    : ep.dlg.writePasswordFrm.val(),
      "isSaveable" : ep.isSaveable,
      "readOnly"   : false
    },
    function(data) {
      if (data.error) {
        alert(data.error);
      } else {
        ep.security_fill(data);
        ep.dlg.dlg.dialog('close');
        jQuery('.pad-iframe').attr("src",data.url);
      }
    }
  );
  return false;
}

ep.security_fill = function(data) {
  if (!data.canPassword) {
    jQuery('.pad-security').hide();
  } else {
    jQuery('.pad-security').show();
  }

  ep.dlg.encAMode.val(data.encAMode);
  ep.dlg.readMode.val(data.readMode);
  ep.dlg.writeMode.val(data.writeMode);
  ep.dlg.readPasswordFrm.val(data.readpw);
  ep.dlg.writePasswordFrm.val(data.writepw);
  ep.readOnly = data.isReadonly;

  ep.on_security_readmode_changed();
  ep.on_security_writemode_changed();

  if (ep.dlg.writePasswordFrm.val() != "") {
    ep.setIcon(jQuery(".pad-security"), "lock"); // lock2
  } else if (ep.dlg.readPasswordFrm.val() != "") {
    ep.setIcon(jQuery(".pad-security"), "lock"); // lock1
  } else {
    ep.setIcon(jQuery(".pad-security"), "lock-open");
  }
  if (ep.readOnly) {
    ep.setIcon(jQuery(".pad-saveable"), "save-off");
  } else {
    ep.setIcon(jQuery(".pad-saveable"), "save");
  }

}

ep.refresh = function() {
  jQuery.post(
      DOKU_BASE + 'lib/exe/ajax.php',
      { 'id' : ep.config["id"], "rev" : ep.config["rev"], "call" : "pad_getText", "isSaveable" : ep.isSaveable, "readOnly"   : false },
      function(data) {
          if (data.error) {
             alert(data.error);
          } else {
             jQuery('#wiki__text').val(data.text);
             if (dw_locktimer) {
               dw_locktimer.refresh();
             }
          }
      }
    );
};

ep.on_security_writemode_changed = function() {
  if(ep.dlg.writeMode.val().indexOf('password') == -1) {
    ep.dlg.writePassword.hide();
  } else {
    ep.dlg.writePassword.show();
  }
}

ep.on_security_readmode_changed = function() {
  if(ep.dlg.readMode.val().indexOf('password') == -1) {
    ep.dlg.readPassword.hide();
  } else {
    ep.dlg.readPassword.show();
  }
}

ep.on_enable_password = function(txt) {
  alert(txt);
  ep.on_password();
}

ep.on_enable = function() {
  console.log("on_enable");
  return ep.on_re_enable(false);
}

ep.aceShow = function() {
  jQuery('img.ace-toggle[src*="off"]:visible').click();
}

ep.aceHide = function() {
  jQuery('img.ace-toggle[src*="on"]:visible').click();
}

ep.aceIsEnabled = function() {
  return (jQuery('img.ace-toggle[src*="on"]:visible').length > 0);
}

ep.cmShow = function() {
  var $toggleLi = jQuery('.cm-settings-menu > li:last-child');
  if ($toggleLi.find('span.ui-icon-check').length == 0) {return;}
  $toggleLi.find('a').click();
}

ep.cmHide = function() {
  var $toggleLi = jQuery('.cm-settings-menu > li:last-child');
  if ($toggleLi.find('span.ui-icon-check').length > 0) {return;}
  $toggleLi.find('a').click();
}

ep.cmIsEnabled = function() {
  var $toggleLi = jQuery('.cm-settings-menu > li:last-child');
  return ($toggleLi.find('span.ui-icon-check').length == 0);
}

ep.on_re_enable = function(reopen) {
  if (!reopen) {
    /* disable ACE, cache it => text is in wiki__text, ace can be restored. */
    ep.aceWasEnabled = ep.aceIsEnabled();
    ep.cmWasEnabled = ep.cmIsEnabled();
  }
  console.log("huhu1");

  ep.aceHide();
  ep.cmHide();

  console.log("huhu2");

  self.setTimeout(ep.on_re_enable_cont, 500);
}

ep.on_re_enable_cont = function() {
  console.log("on_re_enable_cont");
  var text = "";
  if (ep.isSaveable) {
      text = jQuery('#wiki__text').val();
  }
  /* commit */
  console.log("huhu3");
  console.log(ep.config);
  jQuery.post(
      DOKU_BASE + 'lib/exe/ajax.php',
      { 'id' : ep.config["id"], "rev" : ep.config["rev"], "call" : "pad_open", "text" : text,
        'sectok' : jQuery('input[name=sectok]').val(),
        "isSaveable" : ep.isSaveable, "accessPassword" : ep.password },
      function(data) {
          console.log("4");
          if (data.error) {
             if (data.askPassword) {
               ep.on_enable_password(data.error);
             } else {
               alert(data.error);
             }
          } else {
             console.log("5");
             ep.isOwner = data.isOwner;
             ep.opened = true;
             console.log("open: true; re_enable_cont");
             document.cookie="sessionID="+data.sessionID+";domain="+data.domain+";path=/";
             jQuery('.pad-toggle').hide();
             jQuery('.pad-toggle-on').show();
             var htext = (ep.isOwner ? ep.lang.padowner : ep.lang.padnoowner);
             htext = htext.replace(/%s/, ep.config["id"]);
             htext = htext.replace(/%d/, ep.config["rev"]);
             jQuery('.pad-toolbar-label').html(htext);

             h = screen.height - 500;
	           if (h < 300) {
               h = 300;
             }
             if (jQuery('#wiki__text').length == 0) {
               console.log("Missing Wiki Text Field");
             }
             jQuery('#wiki__text').hide();
             jQuery(".pad-action-buttons").show();
             jQuery(".nopad-action-buttons").hide();
             jQuery('.ace-toggle:visible').addClass('ace-toggle-hidden').hide();
             jQuery('.pad-iframecontainer').empty();
             jQuery('<iframe/>').addClass("pad-iframe").attr("src",data.url).appendTo(jQuery('.pad-iframecontainer'));
    	       jQuery('.pad-resizable').resizable();
             if (!ep.isSaveable) { // fix toolbar
               jQuery('#wiki__text').removeAttr('readOnly');
               initToolbar('tool__bar','wiki__text',toolbar);
             }
             ep.security_fill(data);
             if (ep.isOwner) {
                 ep.timer = window.setInterval(ep.refresh, 5 * 60 * 1000);
             }
          }
      }
  );
};

ep.sendMessage = function(func, data) {
  if (ep.hasPadPlugin) {
    var msg = new Object();
    msg.func = func;
    msg.data = data;
    jQuery('iframe.pad-iframe')[0].contentWindow.postMessage(msg, "*");
  } else {
    alert(ep.lang.missingPlugin);
  }
}

ep.proxyGetSelection = function(textArea) {
  if (ep.opened) {
    alert(ep.lang.noGetSelection);
  } else {
    return ep.getSelection.apply(self, [textArea]);
  }
};

ep.proxySetSelection = function(selection) {
  if (ep.opened) {
    alert(ep.lang.noSetSelection);
  } else {
    return ep.setSelection.apply(self, [selection]);
  }
}

ep.proxyDWgetSelection = function(textArea) {
  if (ep.opened) {
    alert(ep.lang.noGetSelection);
  } else {
    return ep.DWgetSelection(textArea);
  }
};

ep.proxyDWsetSelection = function(selection) {
  if (ep.opened) {
    alert(ep.lang.noSetSelection);
  } else {
    return ep.DWsetSelection(selection);
  }
}

ep.proxyPasteText = function (selection,text,opts) {
  if (typeof(text) == 'undefined') return;

  if (ep.opened) {
    alert(ep.lang.noPasteText);
  } else {
    return ep.pasteText.apply(self,[selection,text,opts]);
  }
}

ep.proxyInsertTags = function(textAreaID, tagOpen, tagClose, sampleText) {
  if (ep.opened) {
    ep.sendMessage('insertTags', {'tagOpen': tagOpen, 'tagClose' : tagClose, 'sampleText' : sampleText, 'trimSpaces' : false});
  } else {
    return ep.insertTags.apply(this, [textAreaID, tagOpen, tagClose, sampleText]);
  }
}

ep.proxyInsertAtCarret = function(textAreaID, text) {
  if (ep.opened) {
    ep.sendMessage('insert', {'text': text});
  } else {
    return ep.insertAtCarret.apply(self, [textAreaID, text]);
  }
}

// needed
ep.proxyTbFormatLn = function(btn, props, edid) {
  if (ep.opened) {
    var sample = props.title || props.sample;

    sample = fixtxt(sample);
    props.open  = fixtxt(props.open);
    props.close = fixtxt(props.close);

    ep.sendMessage('insertTagsLn', {'tagOpen': props.open, 'tagClose' : props.close, 'sampleText' : sample});

    pickerClose();
    return false;
  } else {
    return ep.tb_formatln.apply(self, [btn, props, edid]);
  }
}

// needed
ep.proxyInsertLink = function(title) {
  if (ep.opened) {
    var link = dw_linkwiz.$entry.val();
    if(!link) {
      return;
    }
    if(dw_linkwiz.textArea.form.id.value.indexOf(':') != -1 &&
       link.indexOf(':') == -1){
      link = ':' + link;
    }
    ep.sendMessage('insertTags', {'tagOpen': '[['+link+'|', 'tagClose' : ']]', 'sampleText' : title, 'trimSpaces' : true});
    dw_linkwiz.hide();
    dw_linkwiz.$entry.val(dw_linkwiz.$entry.val().replace(/[^:]*$/, ''));
  } else {
    return ep.insertLink(title);
  }
}

ep.onSave = function(event) {
  event.preventDefault();
  event.stopPropagation();
  if (ep.opened) {
    if (confirm("Das Pad muss zunächst geschlossen werden, bevor die Seite gespeichert werden kann.\nSoll das Pad geschlossen werden?")) {
      ep.on_disable(event);
    }
    return false;
  } else {
    return jQuery('#edbtn__save').click();
  }
}

ep.onPreview = function(event) {
  event.preventDefault();
  event.stopPropagation();
  if (ep.opened) {
    alert(ep.lang.noPreview);
    return false;
  } else {
    return jQuery('#edbtn__preview').click();
  }
}

ep.onCancel = function(event) {
  event.preventDefault();
  event.stopPropagation();
  if (ep.opened) {
    if (confirm("Das Pad muss zunächst geschlossen werden, bevor das Bearbeiten der Seite abgebrochen werden kann.\nSoll das Pad geschlossen werden?")) {
      ep.on_disable(event);
    }
    return false;
  } else {
    return jQuery('#edbtn__cancel').click();
  }
}

/* init */

ep.initialize = function() {
  ep.lang = LANG.plugins.etherpadlite;
  ep.isSaveable = (ep.config["act"] != "locked");
  if (jQuery("#size__ctl").length == 0) {
    console.log("Missing #size__ctl");
  }
  ep.makeIcon("pad-toggle pad-toggle-off", "pencil").insertAfter(jQuery("#size__ctl")).click(ep.on_enable);
  ep.makeIcon("pad-toggle pad-toggle-on", "pencil-off").insertAfter(jQuery("#size__ctl")).click(ep.on_disable);
  jQuery("#edbtn__save").clone().attr('id','edbtn__save2').insertAfter('#edbtn__save').click(ep.onSave);
  jQuery("#edbtn__save").addClass("nopad-action-buttons");
  jQuery("#edbtn__save2").addClass("pad-action-buttons");
  jQuery("#edbtn__save2").css("background-image", jQuery("#edbtn__save").css("background-image"));
  jQuery("#edbtn__preview").clone().attr('id','edbtn__preview2').insertAfter('#edbtn__preview').click(ep.onPreview);
  jQuery("#edbtn__preview").addClass("nopad-action-buttons");
  jQuery("#edbtn__preview2").addClass("pad-action-buttons");
  jQuery("#edbtn__preview2").css("background-image", jQuery("#edbtn__preview").css("background-image"));
  jQuery('input.button[type="submit"][name="do[draftdel]"]').attr('id','edbtn__cancel');
  jQuery('#edbtn__cancel').clone().attr('id','edbtn__cancel2').insertAfter('#edbtn__cancel').click(ep.onCancel);
  jQuery("#edbtn__cancel").addClass("nopad-action-buttons");
  jQuery("#edbtn__cancel2").addClass("pad-action-buttons");
  jQuery("#edbtn__cancel2").css("background-image", jQuery("#edbtn__cancel").css("background-image"));
  jQuery('.pad-toggle').hide();
  jQuery('.pad-toggle-off').show();
  jQuery('<div/>').addClass("pad-iframecontainer pad-action-buttons pad-resizable").insertAfter(jQuery('#wiki__text'));
  jQuery('<div/>').addClass("pad-toolbar pad-action-buttons").insertAfter(jQuery('.toolbar'));
  jQuery("<span/>").addClass("pad-toolbar-label").appendTo(jQuery(".pad-toolbar"));
  ep.makeIcon("pad-close", "circle-x").appendTo(jQuery(".pad-toolbar")).click(ep.on_disable);
  ep.makeIcon("pad-security", "lock-open").appendTo(jQuery(".pad-toolbar")).click(ep.on_security);
  ep.makeIcon("pad-saveable", "save-off").appendTo(jQuery(".pad-toolbar")).click(ep.on_password_click);
  jQuery(".pad-action-buttons").hide();
  ep.init_security();
  ep.init_password();
  // check if pad exists -> open it
  if (ep.config["rev"] !== false && ep.config["rev"] > 0) {
    jQuery.post(
      DOKU_BASE + 'lib/exe/ajax.php',
      { 'id' : ep.config["id"], "rev" : ep.config["rev"], "call" : "has_pad",
        "isSaveable" : ep.isSaveable, "accessPassword" : ep.password },
      function(data) {
          if (data.error) {
            alert(data.error);
          } else if (data.exists) {
            console.log("auto-start pad");
            ep.on_enable();
          }
      }
    );
  }
};

ep.iframeinsertReceiveMessage = function(event) {
  if (typeof(event.data) != 'object') {
    return;
  }
  var data = event.data;
  if (data.func == 'none' && data.context == 'ep_iframeinsert') {
    ep.hasPadPlugin = true;
    if (ep.opened) {
      jQuery('#wiki__text').val(data.text);
      self.textChanged = true;
    }
    event.preventDefault();
    event.stopPropagation();
  }
}


window.addEventListener('DOMContentLoaded', (event) => {
  /* textselection / toolbar wrapper */
  ep.setSelection = self.setSelection;
  ep.getSelection = self.getSelection;
  ep.DWsetSelection = self.DWsetSelection;
  ep.DWgetSelection = self.DWgetSelection;
  ep.pasteText = self.pasteText;
  ep.insertTags = self.insertTags;
  ep.insertAtCarret = self.insertAtCarret;
  ep.tb_formatln = self.tb_formatln;
  ep.insertLink = dw_linkwiz.insertLink;

  self.getSelection = ep.proxyGetSelection;
  self.setSelection = ep.proxySetSelection;
  self.DWgetSelection = ep.proxyDWgetSelection;
  self.DWsetSelection = ep.proxyDWsetSelection;
  self.pasteText = ep.proxyPasteText;
  self.insertTags = ep.proxyInsertTags;
  self.insertAtCarret = ep.proxyInsertAtCarret;
  self.tb_formatln = ep.proxyTbFormatLn;
  dw_linkwiz.insertLink = ep.proxyInsertLink;

  self.setTimeout(ep.initialize, 500);

  window.addEventListener("message", ep.iframeinsertReceiveMessage, false);
});

