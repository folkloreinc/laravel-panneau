flklrJsonp([160],{449:function(e,t){ace.define("ace/mode/folding/cstyle",["require","exports","module","ace/lib/oop","ace/range","ace/mode/folding/fold_mode"],function(e,t,i){"use strict";var n=e("../../lib/oop"),o=e("../../range").Range,r=e("./fold_mode").FoldMode,s=t.FoldMode=function(e){e&&(this.foldingStartMarker=new RegExp(this.foldingStartMarker.source.replace(/\|[^|]*?$/,"|"+e.start)),this.foldingStopMarker=new RegExp(this.foldingStopMarker.source.replace(/\|[^|]*?$/,"|"+e.end)))};n.inherits(s,r),function(){this.foldingStartMarker=/(\{|\[)[^\}\]]*$|^\s*(\/\*)/,this.foldingStopMarker=/^[^\[\{]*(\}|\])|^[\s\*]*(\*\/)/,this.singleLineBlockCommentRe=/^\s*(\/\*).*\*\/\s*$/,this.tripleStarBlockCommentRe=/^\s*(\/\*\*\*).*\*\/\s*$/,this.startRegionRe=/^\s*(\/\*|\/\/)#?region\b/,this._getFoldWidgetBase=this.getFoldWidget,this.getFoldWidget=function(e,t,i){var n=e.getLine(i);if(this.singleLineBlockCommentRe.test(n)&&!this.startRegionRe.test(n)&&!this.tripleStarBlockCommentRe.test(n))return"";var o=this._getFoldWidgetBase(e,t,i);return!o&&this.startRegionRe.test(n)?"start":o},this.getFoldWidgetRange=function(e,t,i,n){var o,r=e.getLine(i);if(this.startRegionRe.test(r))return this.getCommentRegionBlock(e,r,i);if(o=r.match(this.foldingStartMarker)){var s=o.index;if(o[1])return this.openingBracketBlock(e,o[1],i,s);var a=e.getCommentFoldRange(i,s+o[0].length,1);return a&&!a.isMultiLine()&&(n?a=this.getSectionRange(e,i):"all"!=t&&(a=null)),a}if("markbegin"!==t&&(o=r.match(this.foldingStopMarker))){s=o.index+o[0].length;return o[1]?this.closingBracketBlock(e,o[1],i,s):e.getCommentFoldRange(i,s,-1)}},this.getSectionRange=function(e,t){for(var i=e.getLine(t),n=i.search(/\S/),r=t,s=i.length,a=t+=1,g=e.getLength();++t<g;){var l=(i=e.getLine(t)).search(/\S/);if(-1!==l){if(n>l)break;var d=this.getFoldWidgetRange(e,"all",t);if(d){if(d.start.row<=r)break;if(d.isMultiLine())t=d.end.row;else if(n==l)break}a=t}}return new o(r,s,a,e.getLine(a).length)},this.getCommentRegionBlock=function(e,t,i){for(var n=t.search(/\s*$/),r=e.getLength(),s=i,a=/^\s*(?:\/\*|\/\/|--)#?(end)?region\b/,g=1;++i<r;){t=e.getLine(i);var l=a.exec(t);if(l&&(l[1]?g--:g++,!g))break}if(i>s)return new o(s,n,i,t.length)}}.call(s.prototype)}),ace.define("ace/mode/mavens_mate_log",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/mavens_mate_log_highlight_rules","ace/mode/folding/cstyle"],function(e,t,i){"use strict";var n=e("../lib/oop"),o=e("./text").Mode,r=e("./mavens_mate_log_highlight_rules").MavensMateLogHighlightRules,s=e("./folding/cstyle").FoldMode,a=function(){this.HighlightRules=r,this.foldingRules=new s};n.inherits(a,o),function(){this.$id="ace/mode/mavens_mate_log"}.call(a.prototype),t.Mode=a})}});