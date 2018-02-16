flklrJsonp([172],{427:function(e,t){ace.define("ace/mode/doc_comment_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,n){"use strict";var i=e("../lib/oop"),o=e("./text_highlight_rules").TextHighlightRules,r=function(){this.$rules={start:[{token:"comment.doc.tag",regex:"@[\\w\\d_]+"},r.getTagRule(),{defaultToken:"comment.doc",caseInsensitive:!0}]}};i.inherits(r,o),r.getTagRule=function(e){return{token:"comment.doc.tag.storage.type",regex:"\\b(?:TODO|FIXME|XXX|HACK)\\b"}},r.getStartRule=function(e){return{token:"comment.doc",regex:"\\/\\*(?=\\*)",next:e}},r.getEndRule=function(e){return{token:"comment.doc",regex:"\\*\\/",next:e}},t.DocCommentHighlightRules=r}),ace.define("ace/mode/lean_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/doc_comment_highlight_rules","ace/mode/text_highlight_rules"],function(e,t,n){"use strict";var i=e("../lib/oop"),o=e("./doc_comment_highlight_rules").DocCommentHighlightRules,r=e("./text_highlight_rules").TextHighlightRules,a=function(){var e=["add_rewrite","alias","as","assume","attribute","begin","by","calc","calc_refl","calc_subst","calc_trans","check","classes","coercions","conjecture","constants","context","corollary","else","end","environment","eval","example","exists","exit","export","exposing","extends","fields","find_decl","forall","from","fun","have","help","hiding","if","import","in","infix","infixl","infixr","instances","let","local","match","namespace","notation","obtain","obtains","omit","opaque","open","options","parameter","parameters","postfix","precedence","prefix","premise","premises","print","private","proof","protected","qed","raw","renaming","section","set_option","show","tactic_hint","take","then","universe","universes","using","variable","variables","with"].join("|"),t=["inductive","structure","record","theorem","axiom","axioms","lemma","hypothesis","definition","constant"].join("|"),n=["Prop","Type","Type'","Type₊","Type₁","Type₂","Type₃"].join("|"),i="\\[("+["abbreviations","all-transparent","begin-end-hints","class","classes","coercion","coercions","declarations","decls","instance","irreducible","multiple-instances","notation","notations","parsing-only","persistent","reduce-hints","reducible","tactic-hints","visible","wf","whnf"].join("|")+")\\]",r=[].join("|"),a=this.$keywords=this.createKeywordMapper({"keyword.control":e,"storage.type":n,"keyword.operator":r,"variable.language":"sorry"},"identifier"),s="[A-Za-z_α-κμ-ϻἀ-῾℀-⅏][A-Za-z0-9_'α-κμ-ϻἀ-῾⁰-⁹ⁿ-₉ₐ-ₜ℀-⅏]*",c=new RegExp(["#","@","->","∼","↔","/","==","=",":=","<->","/\\","\\/","∧","∨","≠","<",">","≤","≥","¬","<=",">=","⁻¹","⬝","▸","\\+","\\*","-","/","λ","→","∃","∀",":="].join("|"));this.$rules={start:[{token:"comment",regex:"--.*$"},o.getStartRule("doc-start"),{token:"comment",regex:"\\/-",next:"comment"},{stateName:"qqstring",token:"string.start",regex:'"',next:[{token:"string.end",regex:'"',next:"start"},{token:"constant.language.escape",regex:/\\[n"\\]/},{defaultToken:"string"}]},{token:"keyword.control",regex:t,next:[{token:"variable.language",regex:s,next:"start"}]},{token:"constant.numeric",regex:"0[xX][0-9a-fA-F]+(L|l|UL|ul|u|U|F|f|ll|LL|ull|ULL)?\\b"},{token:"constant.numeric",regex:"[+-]?\\d+(?:(?:\\.\\d*)?(?:[eE][+-]?\\d+)?)?(L|l|UL|ul|u|U|F|f|ll|LL|ull|ULL)?\\b"},{token:"storage.modifier",regex:i},{token:a,regex:s},{token:"operator",regex:c},{token:"punctuation.operator",regex:"\\?|\\:|\\,|\\;|\\."},{token:"paren.lparen",regex:"[[({]"},{token:"paren.rparen",regex:"[\\])}]"},{token:"text",regex:"\\s+"}],comment:[{token:"comment",regex:"-/",next:"start"},{defaultToken:"comment"}]},this.embedRules(o,"doc-",[o.getEndRule("start")]),this.normalizeRules()};i.inherits(a,r),t.leanHighlightRules=a}),ace.define("ace/mode/matching_brace_outdent",["require","exports","module","ace/range"],function(e,t,n){"use strict";var i=e("../range").Range,o=function(){};(function(){this.checkOutdent=function(e,t){return!!/^\s+$/.test(e)&&/^\s*\}/.test(t)},this.autoOutdent=function(e,t){var n=e.getLine(t).match(/^(\s*\})/);if(!n)return 0;var o=n[1].length,r=e.findMatchingBracket({row:t,column:o});if(!r||r.row==t)return 0;var a=this.$getIndent(e.getLine(r.row));e.replace(new i(t,0,t,o-1),a)},this.$getIndent=function(e){return e.match(/^\s*/)[0]}}).call(o.prototype),t.MatchingBraceOutdent=o}),ace.define("ace/mode/lean",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/lean_highlight_rules","ace/mode/matching_brace_outdent","ace/range"],function(e,t,n){"use strict";var i=e("../lib/oop"),o=e("./text").Mode,r=e("./lean_highlight_rules").leanHighlightRules,a=e("./matching_brace_outdent").MatchingBraceOutdent,s=(e("../range").Range,function(){this.HighlightRules=r,this.$outdent=new a});i.inherits(s,o),function(){this.lineCommentStart="--",this.blockComment={start:"/-",end:"-/"},this.getNextLineIndent=function(e,t,n){var i=this.$getIndent(t),o=this.getTokenizer().getLineTokens(t,e),r=o.tokens,a=o.state;if(r.length&&"comment"==r[r.length-1].type)return i;if("start"==e)(s=t.match(/^.*[\{\(\[]\s*$/))&&(i+=n);else if("doc-start"==e){if("start"==a)return"";var s;(s=t.match(/^\s*(\/?)\*/))&&(s[1]&&(i+=" "),i+="- ")}return i},this.checkOutdent=function(e,t,n){return this.$outdent.checkOutdent(t,n)},this.autoOutdent=function(e,t,n){this.$outdent.autoOutdent(t,n)},this.$id="ace/mode/lean"}.call(s.prototype),t.Mode=s})}});