webpackJsonppanneau([114],{476:function(e,t){ace.define("ace/mode/vhdl_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,r){"use strict";var i=e("../lib/oop"),o=e("./text_highlight_rules").TextHighlightRules,n=function(){var e=this.createKeywordMapper({"keyword.operator":"abs|and|mod|nand|nor|not|rem|rol|ror|sla|sll|srasrl|xnor|xor",keyword:"access|after|ailas|all|architecture|assert|attribute|begin|block|buffer|bus|case|component|configuration|disconnect|downto|else|elsif|end|entity|file|for|function|generate|generic|guarded|if|impure|in|inertial|inout|is|label|linkage|literal|loop|mapnew|next|of|on|open|others|out|port|process|pure|range|record|reject|report|return|select|shared|subtype|then|to|transport|type|unaffected|united|until|wait|when|while|with","constant.language":"true|false|null","storage.modifier":"array|constant","storage.type":"bit|bit_vector|boolean|character|integer|line|natural|positive|real|register|severity|signal|signed|std_logic|std_logic_vector|string||text|time|unsigned|variable"},"identifier",!0);this.$rules={start:[{token:"comment",regex:"--.*$"},{token:"string",regex:'".*?"'},{token:"string",regex:"'.*?'"},{token:"constant.numeric",regex:"[+-]?\\d+(?:(?:\\.\\d*)?(?:[eE][+-]?\\d+)?)?\\b"},{token:"keyword",regex:"\\s*(?:library|package|use)\\b"},{token:e,regex:"[a-zA-Z_$][a-zA-Z0-9_$]*\\b"},{token:"keyword.operator",regex:"&|\\*|\\+|\\-|\\/|<|=|>|\\||=>|\\*\\*|:=|\\/=|>=|<=|<>"},{token:"punctuation.operator",regex:"\\'|\\:|\\,|\\;|\\."},{token:"paren.lparen",regex:"[[(]"},{token:"paren.rparen",regex:"[\\])]"},{token:"text",regex:"\\s+"}]}};i.inherits(n,o),t.VHDLHighlightRules=n}),ace.define("ace/mode/vhdl",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/vhdl_highlight_rules"],function(e,t,r){"use strict";var i=e("../lib/oop"),o=e("./text").Mode,n=e("./vhdl_highlight_rules").VHDLHighlightRules,a=function(){this.HighlightRules=n,this.$behaviour=this.$defaultBehaviour};i.inherits(a,o),function(){this.lineCommentStart="--",this.$id="ace/mode/vhdl"}.call(a.prototype),t.Mode=a})}});
//# sourceMappingURL=vhdl.chunk.js.map