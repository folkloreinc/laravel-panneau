flklrJsonp([77],{325:function(e,t,a){"use strict";t.__esModule=!0;var l=_interopRequireDefault(a(8)),u=_interopRequireDefault(a(11)),n=_interopRequireDefault(a(3)),r=_interopRequireDefault(a(6)),i=_interopRequireDefault(a(7)),d=_interopRequireDefault(a(0)),f=_interopRequireDefault(a(1));function _interopRequireDefault(e){return e&&e.__esModule?e:{default:e}}var o=function(e){function Handle(){return(0,n.default)(this,Handle),(0,r.default)(this,e.apply(this,arguments))}return(0,i.default)(Handle,e),Handle.prototype.focus=function(){this.handle.focus()},Handle.prototype.blur=function(){this.handle.blur()},Handle.prototype.render=function(){var e=this,t=this.props,a=t.className,n=t.vertical,r=t.offset,i=t.style,f=t.disabled,o=t.min,s=t.max,p=t.value,c=t.tabIndex,m=(0,u.default)(t,["className","vertical","offset","style","disabled","min","max","value","tabIndex"]),b=n?{bottom:r+"%"}:{left:r+"%"},v=(0,l.default)({},i,b),_={};return void 0!==p&&(_=(0,l.default)({},_,{"aria-valuemin":o,"aria-valuemax":s,"aria-valuenow":p,"aria-disabled":!!f})),d.default.createElement("div",(0,l.default)({ref:function(t){return e.handle=t},role:"slider",tabIndex:c||0},_,m,{className:a,style:v}))},Handle}(d.default.Component);t.default=o,o.propTypes={className:f.default.string,vertical:f.default.bool,offset:f.default.number,style:f.default.object,disabled:f.default.bool,min:f.default.number,max:f.default.number,value:f.default.number,tabIndex:f.default.number},e.exports=t.default}});