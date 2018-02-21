flklrJsonp([211],{371:function(e,t){ace.define("ace/mode/c9search_highlight_rules",["require","exports","module","ace/lib/oop","ace/lib/lang","ace/mode/text_highlight_rules"],function(e,t,n){"use strict";var r=e("../lib/oop"),i=e("../lib/lang"),s=e("./text_highlight_rules").TextHighlightRules;var a=function(){this.$rules={start:[{tokenNames:["c9searchresults.constant.numeric","c9searchresults.text","c9searchresults.text","c9searchresults.keyword"],regex:/(^\s+[0-9]+)(:)(\d*\s?)([^\r\n]+)/,onMatch:function(e,t,n){var r=this.splitRegex.exec(e),i=this.tokenNames,s=[{type:i[0],value:r[1]},{type:i[1],value:r[2]}];r[3]&&(" "==r[3]?s[1]={type:i[1],value:r[2]+" "}:s.push({type:i[1],value:r[3]}));var a,o=n[1],c=r[4],u=0;if(o&&o.exec)for(o.lastIndex=0;a=o.exec(c);){var h=c.substring(u,a.index);if(u=o.lastIndex,h&&s.push({type:i[2],value:h}),a[0])s.push({type:i[3],value:a[0]});else if(!h)break}return u<c.length&&s.push({type:i[2],value:c.substr(u)}),s}},{regex:"^Searching for [^\\r\\n]*$",onMatch:function(e,t,n){var r,s,a,o=e.split("");if(o.length<3)return"text";var c=0,u=[{value:o[c++]+"'",type:"text"},{value:s=o[c++],type:"text"},{value:"'"+o[c++],type:"text"}];for(" in"!==o[2]&&(a=o[c],u.push({value:"'"+o[c++]+"'",type:"text"},{value:o[c++],type:"text"})),u.push({value:" "+o[c++]+" ",type:"text"}),o[c+1]?(r=o[c+1],u.push({value:"("+o[c+1]+")",type:"text"}),c+=1):c-=1;c++<o.length;)o[c]&&u.push({value:o[c],type:"text"});a&&(s=a,r=""),s&&(/regex/.test(r)||(s=i.escapeRegExp(s)),/whole/.test(r)&&(s="\\b"+s+"\\b"));var h=s&&function(e,t){try{return new RegExp(e,t)}catch(e){}}("("+s+")",/ sensitive/.test(r)?"g":"ig");return h&&(n[0]=t,n[1]=h),u}},{regex:"^(?=Found \\d+ matches)",token:"text",next:"numbers"},{token:"string",regex:"^\\S:?[^:]+",next:"numbers"}],numbers:[{regex:"\\d+",token:"constant.numeric"},{regex:"$",token:"text",next:"start"}]},this.normalizeRules()};r.inherits(a,s),t.C9SearchHighlightRules=a}),ace.define("ace/mode/matching_brace_outdent",["require","exports","module","ace/range"],function(e,t,n){"use strict";var r=e("../range").Range,i=function(){};(function(){this.checkOutdent=function(e,t){return!!/^\s+$/.test(e)&&/^\s*\}/.test(t)},this.autoOutdent=function(e,t){var n=e.getLine(t).match(/^(\s*\})/);if(!n)return 0;var i=n[1].length,s=e.findMatchingBracket({row:t,column:i});if(!s||s.row==t)return 0;var a=this.$getIndent(e.getLine(s.row));e.replace(new r(t,0,t,i-1),a)},this.$getIndent=function(e){return e.match(/^\s*/)[0]}}).call(i.prototype),t.MatchingBraceOutdent=i}),ace.define("ace/mode/folding/c9search",["require","exports","module","ace/lib/oop","ace/range","ace/mode/folding/fold_mode"],function(e,t,n){"use strict";var r=e("../../lib/oop"),i=e("../../range").Range,s=e("./fold_mode").FoldMode,a=t.FoldMode=function(){};r.inherits(a,s),function(){this.foldingStartMarker=/^(\S.*:|Searching for.*)$/,this.foldingStopMarker=/^(\s+|Found.*)$/,this.getFoldWidgetRange=function(e,t,n){var r=e.doc.getAllLines(n),s=r[n],a=/^(Found.*|Searching for.*)$/,o=a.test(s)?a:/^(\S.*:|\s*)$/,c=n,u=n;if(this.foldingStartMarker.test(s)){for(var h=n+1,l=e.getLength();h<l&&!o.test(r[h]);h++);u=h}else if(this.foldingStopMarker.test(s)){for(h=n-1;h>=0&&(s=r[h],!o.test(s));h--);c=h}if(c!=u){var g=s.length;return o===a&&(g=s.search(/\(Found[^)]+\)$|$/)),new i(c,g,u,0)}}}.call(a.prototype)}),ace.define("ace/mode/c9search",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/c9search_highlight_rules","ace/mode/matching_brace_outdent","ace/mode/folding/c9search"],function(e,t,n){"use strict";var r=e("../lib/oop"),i=e("./text").Mode,s=e("./c9search_highlight_rules").C9SearchHighlightRules,a=e("./matching_brace_outdent").MatchingBraceOutdent,o=e("./folding/c9search").FoldMode,c=function(){this.HighlightRules=s,this.$outdent=new a,this.foldingRules=new o};r.inherits(c,i),function(){this.getNextLineIndent=function(e,t,n){return this.$getIndent(t)},this.checkOutdent=function(e,t,n){return this.$outdent.checkOutdent(t,n)},this.autoOutdent=function(e,t,n){this.$outdent.autoOutdent(t,n)},this.$id="ace/mode/c9search"}.call(c.prototype),t.Mode=c})}});