webpackJsonppanneau([212],{348:function(e,t){ace.define("ace/mode/bro_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,o){"use strict";var r=e("../lib/oop"),n=e("./text_highlight_rules").TextHighlightRules,i=function(){this.$rules={start:[{token:"punctuation.definition.comment.bro",regex:/#/,push:[{token:"comment.line.number-sign.bro",regex:/$/,next:"pop"},{defaultToken:"comment.line.number-sign.bro"}]},{token:"keyword.control.bro",regex:/\b(?:break|case|continue|else|for|if|return|switch|next|when|timeout|schedule)\b/},{token:["meta.function.bro","meta.function.bro","storage.type.bro","meta.function.bro","entity.name.function.bro","meta.function.bro"],regex:/^(\s*)(?:function|hook|event)(\s*)(.*)(\s*\()(.*)(\).*$)/},{token:"storage.type.bro",regex:/\b(?:bool|enum|double|int|count|port|addr|subnet|any|file|interval|time|string|table|vector|set|record|pattern|hook)\b/},{token:"storage.modifier.bro",regex:/\b(?:global|const|redef|local|&(?:optional|rotate_interval|rotate_size|add_func|del_func|expire_func|expire_create|expire_read|expire_write|persistent|synchronized|encrypt|mergeable|priority|group|type_column|log|error_handler))\b/},{token:"keyword.operator.bro",regex:/\s*(?:\||&&|(?:>|<|!)=?|==)\s*|\b!?in\b/},{token:"constant.language.bro",regex:/\b(?:T|F)\b/},{token:"constant.numeric.bro",regex:/\b(?:0(?:x|X)[0-9a-fA-F]*|(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:(?:e|E)(?:\+|-)?[0-9]+)?)(?:\/(?:tcp|udp|icmp)|\s*(?:u?sec|min|hr|day)s?)?\b/},{token:"punctuation.definition.string.begin.bro",regex:/"/,push:[{token:"punctuation.definition.string.end.bro",regex:/"/,next:"pop"},{include:"#string_escaped_char"},{include:"#string_placeholder"},{defaultToken:"string.quoted.double.bro"}]},{token:"punctuation.definition.string.begin.bro",regex:/\//,push:[{token:"punctuation.definition.string.end.bro",regex:/\//,next:"pop"},{include:"#string_escaped_char"},{include:"#string_placeholder"},{defaultToken:"string.quoted.regex.bro"}]},{token:["meta.preprocessor.bro.load","keyword.other.special-method.bro"],regex:/^(\s*)(\@load(?:-sigs)?)\b/,push:[{token:[],regex:/(?=\#)|$/,next:"pop"},{defaultToken:"meta.preprocessor.bro.load"}]},{token:["meta.preprocessor.bro.if","keyword.other.special-method.bro","meta.preprocessor.bro.if"],regex:/^(\s*)(\@endif|\@if(?:n?def)?)(.*$)/,push:[{token:[],regex:/$/,next:"pop"},{defaultToken:"meta.preprocessor.bro.if"}]}],"#disabled":[{token:"text",regex:/^\s*\@if(?:n?def)?\b.*$/,push:[{token:"text",regex:/^\s*\@endif\b.*$/,next:"pop"},{include:"#disabled"},{include:"#pragma-mark"}],comment:"eat nested preprocessor ifdefs"}],"#preprocessor-rule-other":[{token:["text","meta.preprocessor.bro","meta.preprocessor.bro","text"],regex:/^(\s*)(@if)((?:n?def)?)\b(.*?)(?:(?=)|$)/,push:[{token:["text","meta.preprocessor.bro","text"],regex:/^(\s*)(@endif)\b(.*$)/,next:"pop"},{include:"$base"}]}],"#string_escaped_char":[{token:"constant.character.escape.bro",regex:/\\(?:\\|[abefnprtv'"?]|[0-3]\d{,2}|[4-7]\d?|x[a-fA-F0-9]{,2})/},{token:"invalid.illegal.unknown-escape.bro",regex:/\\./}],"#string_placeholder":[{token:"constant.other.placeholder.bro",regex:/%(?:\d+\$)?[#0\- +']*[,;:_]?(?:-?\d+|\*(?:-?\d+\$)?)?(?:\.(?:-?\d+|\*(?:-?\d+\$)?)?)?(?:hh|h|ll|l|j|t|z|q|L|vh|vl|v|hv|hl)?[diouxXDOUeEfFgGaACcSspn%]/},{token:"invalid.illegal.placeholder.bro",regex:/%/}]},this.normalizeRules()};i.metaData={fileTypes:["bro"],foldingStartMarker:"^(\\@if(n?def)?)",foldingStopMarker:"^\\@endif",keyEquivalent:"@B",name:"Bro",scopeName:"source.bro"},r.inherits(i,n),t.BroHighlightRules=i}),ace.define("ace/mode/folding/cstyle",["require","exports","module","ace/lib/oop","ace/range","ace/mode/folding/fold_mode"],function(e,t,o){"use strict";var r=e("../../lib/oop"),n=e("../../range").Range,i=e("./fold_mode").FoldMode,s=t.FoldMode=function(e){e&&(this.foldingStartMarker=new RegExp(this.foldingStartMarker.source.replace(/\|[^|]*?$/,"|"+e.start)),this.foldingStopMarker=new RegExp(this.foldingStopMarker.source.replace(/\|[^|]*?$/,"|"+e.end)))};r.inherits(s,i),function(){this.foldingStartMarker=/(\{|\[)[^\}\]]*$|^\s*(\/\*)/,this.foldingStopMarker=/^[^\[\{]*(\}|\])|^[\s\*]*(\*\/)/,this.singleLineBlockCommentRe=/^\s*(\/\*).*\*\/\s*$/,this.tripleStarBlockCommentRe=/^\s*(\/\*\*\*).*\*\/\s*$/,this.startRegionRe=/^\s*(\/\*|\/\/)#?region\b/,this._getFoldWidgetBase=this.getFoldWidget,this.getFoldWidget=function(e,t,o){var r=e.getLine(o);if(this.singleLineBlockCommentRe.test(r)&&!this.startRegionRe.test(r)&&!this.tripleStarBlockCommentRe.test(r))return"";var n=this._getFoldWidgetBase(e,t,o);return!n&&this.startRegionRe.test(r)?"start":n},this.getFoldWidgetRange=function(e,t,o,r){var n=e.getLine(o);if(this.startRegionRe.test(n))return this.getCommentRegionBlock(e,n,o);var i=n.match(this.foldingStartMarker);if(i){var s=i.index;if(i[1])return this.openingBracketBlock(e,i[1],o,s);var a=e.getCommentFoldRange(o,s+i[0].length,1);return a&&!a.isMultiLine()&&(r?a=this.getSectionRange(e,o):"all"!=t&&(a=null)),a}if("markbegin"!==t){var i=n.match(this.foldingStopMarker);if(i){var s=i.index+i[0].length;return i[1]?this.closingBracketBlock(e,i[1],o,s):e.getCommentFoldRange(o,s,-1)}}},this.getSectionRange=function(e,t){var o=e.getLine(t),r=o.search(/\S/),i=t,s=o.length;t+=1;for(var a=t,l=e.getLength();++t<l;){o=e.getLine(t);var g=o.search(/\S/);if(-1!==g){if(r>g)break;var d=this.getFoldWidgetRange(e,"all",t);if(d){if(d.start.row<=i)break;if(d.isMultiLine())t=d.end.row;else if(r==g)break}a=t}}return new n(i,s,a,e.getLine(a).length)},this.getCommentRegionBlock=function(e,t,o){for(var r=t.search(/\s*$/),i=e.getLength(),s=o,a=/^\s*(?:\/\*|\/\/|--)#?(end)?region\b/,l=1;++o<i;){t=e.getLine(o);var g=a.exec(t);if(g&&(g[1]?l--:l++,!l))break}var d=o;if(d>s)return new n(s,r,d,t.length)}}.call(s.prototype)}),ace.define("ace/mode/bro",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/bro_highlight_rules","ace/mode/folding/cstyle"],function(e,t,o){"use strict";var r=e("../lib/oop"),n=e("./text").Mode,i=e("./bro_highlight_rules").BroHighlightRules,s=e("./folding/cstyle").FoldMode,a=function(){this.HighlightRules=i,this.foldingRules=new s};r.inherits(a,n),function(){this.$id="ace/mode/bro"}.call(a.prototype),t.Mode=a})}});
//# sourceMappingURL=bro.chunk.js.map