webpackJsonppanneau([376],{792:function(e,t){ace.define("ace/mode/properties_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,i){"use strict";var o=e("../lib/oop"),r=e("./text_highlight_rules").TextHighlightRules,n=function(){var e=/\\u[0-9a-fA-F]{4}|\\/;this.$rules={start:[{token:"comment",regex:/[!#].*$/},{token:"keyword",regex:/[=:]$/},{token:"keyword",regex:/[=:]/,next:"value"},{token:"constant.language.escape",regex:e},{defaultToken:"variable"}],value:[{regex:/\\$/,token:"string",next:"value"},{regex:/$/,token:"string",next:"start"},{token:"constant.language.escape",regex:e},{defaultToken:"string"}]}};o.inherits(n,r),t.PropertiesHighlightRules=n}),ace.define("ace/mode/properties",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/properties_highlight_rules"],function(e,t,i){"use strict";var o=e("../lib/oop"),r=e("./text").Mode,n=e("./properties_highlight_rules").PropertiesHighlightRules,s=function(){this.HighlightRules=n,this.$behaviour=this.$defaultBehaviour};o.inherits(s,r),function(){this.$id="ace/mode/properties"}.call(s.prototype),t.Mode=s})}});