webpackJsonppanneau([441],{862:function(e,t){ace.define("ace/ext/spellcheck",["require","exports","module","ace/lib/event","ace/editor","ace/config"],function(e,t,n){"use strict";var i=e("../lib/event");t.contextMenuHandler=function(e){var t=e.target,n=t.textInput.getElement();if(t.selection.isEmpty()){var s=t.getCursorPosition(),o=t.session.getWordRange(s.row,s.column),c=t.session.getTextRange(o);if(t.session.tokenRe.lastIndex=0,t.session.tokenRe.test(c)){var r=c+" \x01\x01";n.value=r,n.setSelectionRange(c.length,c.length+1),n.setSelectionRange(0,0),n.setSelectionRange(0,c.length);var l=!1;i.addListener(n,"keydown",function e(){i.removeListener(n,"keydown",e),l=!0}),t.textInput.setInputHandler(function(e){if(console.log(e,r,n.selectionStart,n.selectionEnd),e==r)return"";if(0===e.lastIndexOf(r,0))return e.slice(r.length);if(e.substr(n.selectionEnd)==r)return e.slice(0,-r.length);if("\x01\x01"==e.slice(-2)){var i=e.slice(0,-2);if(" "==i.slice(-1))return l?i.substring(0,n.selectionEnd):(i=i.slice(0,-1),t.session.replace(o,i),"")}return e})}}};var s=e("../editor").Editor;e("../config").defineOptions(s.prototype,"editor",{spellcheck:{set:function(e){this.textInput.getElement().spellcheck=!!e,e?this.on("nativecontextmenu",t.contextMenuHandler):this.removeListener("nativecontextmenu",t.contextMenuHandler)},value:!0}})}),function(){ace.acequire(["ace/ext/spellcheck"],function(){})}()}});