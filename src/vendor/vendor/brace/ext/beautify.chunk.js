webpackJsonppanneau([241],{648:function(e,t){ace.define("ace/ext/beautify/php_rules",["require","exports","module","ace/token_iterator"],function(e,t,a){"use strict";e("ace/token_iterator").TokenIterator;t.newLines=[{type:"support.php_tag",value:"<?php"},{type:"support.php_tag",value:"<?"},{type:"support.php_tag",value:"?>"},{type:"paren.lparen",value:"{",indent:!0},{type:"paren.rparen",breakBefore:!0,value:"}",indent:!1},{type:"paren.rparen",breakBefore:!0,value:"})",indent:!1,dontBreak:!0},{type:"comment"},{type:"text",value:";"},{type:"text",value:":",context:"php"},{type:"keyword",value:"case",indent:!0,dontBreak:!0},{type:"keyword",value:"default",indent:!0,dontBreak:!0},{type:"keyword",value:"break",indent:!1,dontBreak:!0},{type:"punctuation.doctype.end",value:">"},{type:"meta.tag.punctuation.end",value:">"},{type:"meta.tag.punctuation.begin",value:"<",blockTag:!0,indent:!0,dontBreak:!0},{type:"meta.tag.punctuation.begin",value:"</",indent:!1,breakBefore:!0,dontBreak:!0},{type:"punctuation.operator",value:";"}],t.spaces=[{type:"xml-pe",prepend:!0},{type:"entity.other.attribute-name",prepend:!0},{type:"storage.type",value:"var",append:!0},{type:"storage.type",value:"function",append:!0},{type:"keyword.operator",value:"="},{type:"keyword",value:"as",prepend:!0,append:!0},{type:"keyword",value:"function",append:!0},{type:"support.function",next:/[^\(]/,append:!0},{type:"keyword",value:"or",append:!0,prepend:!0},{type:"keyword",value:"and",append:!0,prepend:!0},{type:"keyword",value:"case",append:!0},{type:"keyword.operator",value:"||",append:!0,prepend:!0},{type:"keyword.operator",value:"&&",append:!0,prepend:!0}],t.singleTags=["!doctype","area","base","br","hr","input","img","link","meta"],t.transform=function(e,a,p){for(var n,r,o=e.getCurrentToken(),u=t.newLines,l=t.spaces,i=t.singleTags,y="",d=0,s=!1,v={},c={},f=!1,k="";null!==o;)if(console.log(o),o)if("support.php_tag"==o.type&&"?>"!=o.value?p="php":"support.php_tag"==o.type&&"?>"==o.value?p="html":"meta.tag.name.style"==o.type&&"css"!=p?p="css":"meta.tag.name.style"==o.type&&"css"==p?p="html":"meta.tag.name.script"==o.type&&"js"!=p?p="js":"meta.tag.name.script"==o.type&&"js"==p&&(p="html"),c=e.stepForward(),c&&0==c.type.indexOf("meta.tag.name")&&(r=c.value),"support.php_tag"==v.type&&"<?="==v.value&&(s=!0),"meta.tag.name"==o.type&&(o.value=o.value.toLowerCase()),"text"==o.type&&(o.value=o.value.trim()),o.value){k=o.value;for(var m in l)o.type!=l[m].type||l[m].value&&o.value!=l[m].value||!c||l[m].next&&!l[m].next.test(c.value)||(l[m].prepend&&(k=" "+o.value),l[m].append&&(k+=" "));0==o.type.indexOf("meta.tag.name")&&(n=o.value),f=!1;for(m in u)if(o.type==u[m].type&&(!u[m].value||o.value==u[m].value)&&(!u[m].blockTag||-1===i.indexOf(r))&&(!u[m].context||u[m].context===p)){if(!1===u[m].indent&&d--,u[m].breakBefore&&(!u[m].prev||u[m].prev.test(v.value)))for(y+="\n",f=!0,m=0;m<d;m++)y+="\t";break}if(!1===s)for(m in u)if(v.type==u[m].type&&(!u[m].value||v.value==u[m].value)&&(!u[m].blockTag||-1===i.indexOf(n))&&(!u[m].context||u[m].context===p)){if(!0===u[m].indent&&d++,!u[m].dontBreak&&!f)for(y+="\n",m=0;m<d;m++)y+="\t";break}if(y+=k,"support.php_tag"==v.type&&"?>"==v.value&&(s=!1),n,v=o,null===(o=c))break}else o=c;else o=e.stepForward();return y}}),ace.define("ace/ext/beautify",["require","exports","module","ace/token_iterator","ace/ext/beautify/php_rules"],function(e,t,a){"use strict";var p=e("ace/token_iterator").TokenIterator,n=e("./beautify/php_rules").transform;t.beautify=function(e){var t=new p(e,0,0),a=(t.getCurrentToken(),e.$modeId.split("/").pop()),r=n(t,a);e.doc.setValue(r)},t.commands=[{name:"beautify",exec:function(e){t.beautify(e.session)},bindKey:"Ctrl-Shift-B"}]}),function(){ace.acequire(["ace/ext/beautify"],function(){})}()}});