(window.webpackJsonppanneau=window.webpackJsonppanneau||[]).push([[18],{503:function(e,o){ace.define("ace/ext/linking",["require","exports","module","ace/editor","ace/config"],function(e,o,i){var n=e("ace/editor").Editor;function t(e){var o=e.editor;if(e.getAccelKey()){o=e.editor;var i=e.getDocumentPosition(),n=o.session.getTokenAt(i.row,i.column);o._emit("linkHover",{position:i,token:n})}}function c(e){var o=e.getAccelKey();if(0==e.getButton()&&o){var i=e.editor,n=e.getDocumentPosition(),t=i.session.getTokenAt(n.row,n.column);i._emit("linkClick",{position:n,token:t})}}e("../config").defineOptions(n.prototype,"editor",{enableLinking:{set:function(e){e?(this.on("click",c),this.on("mousemove",t)):(this.off("click",c),this.off("mousemove",t))},value:!1}})}),ace.acequire(["ace/ext/linking"],function(){})}}]);