flklrJsonp([128],{488:function(e,t){ace.define("ace/mode/folding/coffee",["require","exports","module","ace/lib/oop","ace/mode/folding/fold_mode","ace/range"],function(e,t,i){"use strict";var o=e("../../lib/oop"),r=e("./fold_mode").FoldMode,n=e("../../range").Range,l=t.FoldMode=function(){};o.inherits(l,r),function(){this.getFoldWidgetRange=function(e,t,i){var o=this.indentationBlock(e,i);if(o)return o;var r=/\S/,l=e.getLine(i),a=l.search(r);if(-1!=a&&"#"==l[a]){for(var s=l.length,g=e.getLength(),d=i,c=i;++i<g;){var h=(l=e.getLine(i)).search(r);if(-1!=h){if("#"!=l[h])break;c=i}}if(c>d){var f=e.getLine(c).length;return new n(d,s,c,f)}}},this.getFoldWidget=function(e,t,i){var o=e.getLine(i),r=o.search(/\S/),n=e.getLine(i+1),l=e.getLine(i-1),a=l.search(/\S/),s=n.search(/\S/);if(-1==r)return e.foldWidgets[i-1]=-1!=a&&a<s?"start":"","";if(-1==a){if(r==s&&"#"==o[r]&&"#"==n[r])return e.foldWidgets[i-1]="",e.foldWidgets[i+1]="","start"}else if(a==r&&"#"==o[r]&&"#"==l[r]&&-1==e.getLine(i-2).search(/\S/))return e.foldWidgets[i-1]="start",e.foldWidgets[i+1]="","";return e.foldWidgets[i-1]=-1!=a&&a<r?"start":"",r<s?"start":""}}.call(l.prototype)}),ace.define("ace/mode/space_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,i){"use strict";var o=e("../lib/oop"),r=e("./text_highlight_rules").TextHighlightRules,n=function(){this.$rules={start:[{token:"empty_line",regex:/ */,next:"key"},{token:"empty_line",regex:/$/,next:"key"}],key:[{token:"variable",regex:/\S+/},{token:"empty_line",regex:/$/,next:"start"},{token:"keyword.operator",regex:/ /,next:"value"}],value:[{token:"keyword.operator",regex:/$/,next:"start"},{token:"string",regex:/[^$]/}]}};o.inherits(n,r),t.SpaceHighlightRules=n}),ace.define("ace/mode/space",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/folding/coffee","ace/mode/space_highlight_rules"],function(e,t,i){"use strict";var o=e("../lib/oop"),r=e("./text").Mode,n=e("./folding/coffee").FoldMode,l=e("./space_highlight_rules").SpaceHighlightRules,a=function(){this.HighlightRules=l,this.foldingRules=new n,this.$behaviour=this.$defaultBehaviour};o.inherits(a,r),function(){this.$id="ace/mode/space"}.call(a.prototype),t.Mode=a})}});