flklrJsonp([190],{398:function(e,i){ace.define("ace/mode/gitignore_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,i,t){"use strict";var o=e("../lib/oop"),r=e("./text_highlight_rules").TextHighlightRules,g=function(){this.$rules={start:[{token:"comment",regex:/^\s*#.*$/},{token:"keyword",regex:/^\s*!.*$/}]},this.normalizeRules()};g.metaData={fileTypes:["gitignore"],name:"Gitignore"},o.inherits(g,r),i.GitignoreHighlightRules=g}),ace.define("ace/mode/gitignore",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/gitignore_highlight_rules"],function(e,i,t){"use strict";var o=e("../lib/oop"),r=e("./text").Mode,g=e("./gitignore_highlight_rules").GitignoreHighlightRules,h=function(){this.HighlightRules=g,this.$behaviour=this.$defaultBehaviour};o.inherits(h,r),function(){this.lineCommentStart="#",this.$id="ace/mode/gitignore"}.call(h.prototype),i.Mode=h})}});