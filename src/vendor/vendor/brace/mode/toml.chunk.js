webpackJsonppanneau([118],{468:function(e,t){ace.define("ace/mode/toml_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,o){"use strict";var i=e("../lib/oop"),n=e("./text_highlight_rules").TextHighlightRules,r=function(){var e=this.createKeywordMapper({"constant.language.boolean":"true|false"},"identifier");this.$rules={start:[{token:"comment.toml",regex:/#.*$/},{token:"string",regex:'"(?=.)',next:"qqstring"},{token:["variable.keygroup.toml"],regex:"(?:^\\s*)(\\[\\[([^\\]]+)\\]\\])"},{token:["variable.keygroup.toml"],regex:"(?:^\\s*)(\\[([^\\]]+)\\])"},{token:e,regex:"[a-zA-Z\\$_\xa1-\uffff][a-zA-Z\\d\\$_\xa1-\uffff]*\\b"},{token:"support.date.toml",regex:"\\d{4}-\\d{2}-\\d{2}(T)\\d{2}:\\d{2}:\\d{2}(Z)"},{token:"constant.numeric.toml",regex:"-?\\d+(\\.?\\d+)?"}],qqstring:[{token:"string",regex:"\\\\$",next:"qqstring"},{token:"constant.language.escape",regex:'\\\\[0tnr"\\\\]'},{token:"string",regex:'"|$',next:"start"},{defaultToken:"string"}]}};i.inherits(r,n),t.TomlHighlightRules=r}),ace.define("ace/mode/folding/ini",["require","exports","module","ace/lib/oop","ace/range","ace/mode/folding/fold_mode"],function(e,t,o){"use strict";var i=e("../../lib/oop"),n=e("../../range").Range,r=e("./fold_mode").FoldMode,l=t.FoldMode=function(){};i.inherits(l,r),function(){this.foldingStartMarker=/^\s*\[([^\])]*)]\s*(?:$|[;#])/,this.getFoldWidgetRange=function(e,t,o){var i=this.foldingStartMarker,r=e.getLine(o),l=r.match(i);if(l){for(var a=l[1]+".",g=r.length,s=e.getLength(),d=o,h=o;++o<s;)if(r=e.getLine(o),!/^\s*$/.test(r)){if((l=r.match(i))&&0!==l[1].lastIndexOf(a,0))break;h=o}if(h>d){var u=e.getLine(h).length;return new n(d,g,h,u)}}}}.call(l.prototype)}),ace.define("ace/mode/toml",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/toml_highlight_rules","ace/mode/folding/ini"],function(e,t,o){"use strict";var i=e("../lib/oop"),n=e("./text").Mode,r=e("./toml_highlight_rules").TomlHighlightRules,l=e("./folding/ini").FoldMode,a=function(){this.HighlightRules=r,this.foldingRules=new l,this.$behaviour=this.$defaultBehaviour};i.inherits(a,n),function(){this.lineCommentStart="#",this.$id="ace/mode/toml"}.call(a.prototype),t.Mode=a})}});
//# sourceMappingURL=toml.chunk.js.map