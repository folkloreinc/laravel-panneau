flklrJsonp([213],{385:function(e,t){ace.define("ace/mode/batchfile_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,i){"use strict";var o=e("../lib/oop"),n=e("./text_highlight_rules").TextHighlightRules,r=function(){this.$rules={start:[{token:"keyword.command.dosbatch",regex:"\\b(?:append|assoc|at|attrib|break|cacls|cd|chcp|chdir|chkdsk|chkntfs|cls|cmd|color|comp|compact|convert|copy|date|del|dir|diskcomp|diskcopy|doskey|echo|endlocal|erase|fc|find|findstr|format|ftype|graftabl|help|keyb|label|md|mkdir|mode|more|move|path|pause|popd|print|prompt|pushd|rd|recover|ren|rename|replace|restore|rmdir|set|setlocal|shift|sort|start|subst|time|title|tree|type|ver|verify|vol|xcopy)\\b",caseInsensitive:!0},{token:"keyword.control.statement.dosbatch",regex:"\\b(?:goto|call|exit)\\b",caseInsensitive:!0},{token:"keyword.control.conditional.if.dosbatch",regex:"\\bif\\s+not\\s+(?:exist|defined|errorlevel|cmdextversion)\\b",caseInsensitive:!0},{token:"keyword.control.conditional.dosbatch",regex:"\\b(?:if|else)\\b",caseInsensitive:!0},{token:"keyword.control.repeat.dosbatch",regex:"\\bfor\\b",caseInsensitive:!0},{token:"keyword.operator.dosbatch",regex:"\\b(?:EQU|NEQ|LSS|LEQ|GTR|GEQ)\\b"},{token:["doc.comment","comment"],regex:"(?:^|\\b)(rem)($|\\s.*$)",caseInsensitive:!0},{token:"comment.line.colons.dosbatch",regex:"::.*$"},{include:"variable"},{token:"punctuation.definition.string.begin.shell",regex:'"',push:[{token:"punctuation.definition.string.end.shell",regex:'"',next:"pop"},{include:"variable"},{defaultToken:"string.quoted.double.dosbatch"}]},{token:"keyword.operator.pipe.dosbatch",regex:"[|]"},{token:"keyword.operator.redirect.shell",regex:"&>|\\d*>&\\d*|\\d*(?:>>|>|<)|\\d*<&|\\d*<>"}],variable:[{token:"constant.numeric",regex:"%%\\w+|%[*\\d]|%\\w+%"},{token:"constant.numeric",regex:"%~\\d+"},{token:["markup.list","constant.other","markup.list"],regex:"(%)(\\w+)(%?)"}]},this.normalizeRules()};r.metaData={name:"Batch File",scopeName:"source.dosbatch",fileTypes:["bat"]},o.inherits(r,n),t.BatchFileHighlightRules=r}),ace.define("ace/mode/folding/cstyle",["require","exports","module","ace/lib/oop","ace/range","ace/mode/folding/fold_mode"],function(e,t,i){"use strict";var o=e("../../lib/oop"),n=e("../../range").Range,r=e("./fold_mode").FoldMode,s=t.FoldMode=function(e){e&&(this.foldingStartMarker=new RegExp(this.foldingStartMarker.source.replace(/\|[^|]*?$/,"|"+e.start)),this.foldingStopMarker=new RegExp(this.foldingStopMarker.source.replace(/\|[^|]*?$/,"|"+e.end)))};o.inherits(s,r),function(){this.foldingStartMarker=/(\{|\[)[^\}\]]*$|^\s*(\/\*)/,this.foldingStopMarker=/^[^\[\{]*(\}|\])|^[\s\*]*(\*\/)/,this.singleLineBlockCommentRe=/^\s*(\/\*).*\*\/\s*$/,this.tripleStarBlockCommentRe=/^\s*(\/\*\*\*).*\*\/\s*$/,this.startRegionRe=/^\s*(\/\*|\/\/)#?region\b/,this._getFoldWidgetBase=this.getFoldWidget,this.getFoldWidget=function(e,t,i){var o=e.getLine(i);if(this.singleLineBlockCommentRe.test(o)&&!this.startRegionRe.test(o)&&!this.tripleStarBlockCommentRe.test(o))return"";var n=this._getFoldWidgetBase(e,t,i);return!n&&this.startRegionRe.test(o)?"start":n},this.getFoldWidgetRange=function(e,t,i,o){var n,r=e.getLine(i);if(this.startRegionRe.test(r))return this.getCommentRegionBlock(e,r,i);if(n=r.match(this.foldingStartMarker)){var s=n.index;if(n[1])return this.openingBracketBlock(e,n[1],i,s);var a=e.getCommentFoldRange(i,s+n[0].length,1);return a&&!a.isMultiLine()&&(o?a=this.getSectionRange(e,i):"all"!=t&&(a=null)),a}if("markbegin"!==t&&(n=r.match(this.foldingStopMarker))){s=n.index+n[0].length;return n[1]?this.closingBracketBlock(e,n[1],i,s):e.getCommentFoldRange(i,s,-1)}},this.getSectionRange=function(e,t){for(var i=e.getLine(t),o=i.search(/\S/),r=t,s=i.length,a=t+=1,l=e.getLength();++t<l;){var c=(i=e.getLine(t)).search(/\S/);if(-1!==c){if(o>c)break;var d=this.getFoldWidgetRange(e,"all",t);if(d){if(d.start.row<=r)break;if(d.isMultiLine())t=d.end.row;else if(o==c)break}a=t}}return new n(r,s,a,e.getLine(a).length)},this.getCommentRegionBlock=function(e,t,i){for(var o=t.search(/\s*$/),r=e.getLength(),s=i,a=/^\s*(?:\/\*|\/\/|--)#?(end)?region\b/,l=1;++i<r;){t=e.getLine(i);var c=a.exec(t);if(c&&(c[1]?l--:l++,!l))break}if(i>s)return new n(s,o,i,t.length)}}.call(s.prototype)}),ace.define("ace/mode/batchfile",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/batchfile_highlight_rules","ace/mode/folding/cstyle"],function(e,t,i){"use strict";var o=e("../lib/oop"),n=e("./text").Mode,r=e("./batchfile_highlight_rules").BatchFileHighlightRules,s=e("./folding/cstyle").FoldMode,a=function(){this.HighlightRules=r,this.foldingRules=new s,this.$behaviour=this.$defaultBehaviour};o.inherits(a,n),function(){this.lineCommentStart="::",this.blockComment="",this.$id="ace/mode/batchfile"}.call(a.prototype),t.Mode=a})}});