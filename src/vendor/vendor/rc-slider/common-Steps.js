flklrJsonp([75],{341:function(e,t,r){"use strict";t.__esModule=!0;var a=_interopRequireDefault(r(3)),u=_interopRequireDefault(r(0)),l=_interopRequireDefault(r(12)),o=_interopRequireDefault(r(17));function _interopRequireDefault(e){return e&&e.__esModule?e:{default:e}}t.default=function(e){var t=e.prefixCls,r=e.vertical,i=e.marks,n=e.dots,d=e.step,f=e.included,s=e.lowerBound,p=e.upperBound,c=e.max,m=e.min,v=e.dotStyle,_=e.activeDotStyle,k=c-m,D=function(e,t,r,a,u,l){(0,o.default)(!r||a>0,"`Slider[step]` should be a positive number in order to make Slider[dots] work.");var i=Object.keys(t).map(parseFloat);if(r)for(var n=u;n<=l;n+=a)i.indexOf(n)>=0||i.push(n);return i}(0,i,n,d,m,c).map(function(e){var o,i=Math.abs(e-m)/k*100+"%",n=!f&&e===p||f&&e<=p&&e>=s,d=r?(0,a.default)({bottom:i},v):(0,a.default)({left:i},v);n&&(d=(0,a.default)({},d,_));var c=(0,l.default)(((o={})[t+"-dot"]=!0,o[t+"-dot-active"]=n,o));return u.default.createElement("span",{className:c,style:d,key:e})});return u.default.createElement("div",{className:t+"-step"},D)},e.exports=t.default}});