webpackJsonppanneau([478],{1630:function(e,n,t){e.exports=t(1773)},1773:function(e,n,t){"use strict";function o(){}Object.defineProperty(n,"__esModule",{value:!0});var c=t(408),s=t.n(c),a=t(449),d=t.n(a),i=t(235),l=t.n(i),h=t(25),u=t.n(h),r=t(26),p=t.n(r),k=t(30),C=t.n(k),f=t(31),b=t.n(f),y=t(0),v=t.n(y),g=t(1),m=t.n(g),x=t(11),M=function(e){function n(e){u()(this,n);var t=C()(this,(n.__proto__||Object.getPrototypeOf(n)).call(this,e));N.call(t);var o=!1;return o="checked"in e?!!e.checked:!!e.defaultChecked,t.state={checked:o},t}return b()(n,e),p()(n,[{key:"componentDidMount",value:function(){var e=this.props,n=e.autoFocus,t=e.disabled;n&&!t&&this.focus()}},{key:"componentWillReceiveProps",value:function(e){"checked"in e&&this.setState({checked:!!e.checked})}},{key:"setChecked",value:function(e){this.props.disabled||("checked"in this.props||this.setState({checked:e}),this.props.onChange(e))}},{key:"focus",value:function(){this.node.focus()}},{key:"blur",value:function(){this.node.blur()}},{key:"render",value:function(){var e,n=this.props,t=n.className,o=n.prefixCls,c=n.disabled,a=n.checkedChildren,i=n.tabIndex,h=n.unCheckedChildren,u=l()(n,["className","prefixCls","disabled","checkedChildren","tabIndex","unCheckedChildren"]),r=this.state.checked,p=c?-1:i||0,k=x((e={},d()(e,t,!!t),d()(e,o,!0),d()(e,o+"-checked",r),d()(e,o+"-disabled",c),e));return v.a.createElement("span",s()({},u,{className:k,tabIndex:p,ref:this.saveNode,onKeyDown:this.handleKeyDown,onClick:this.toggle,onMouseUp:this.handleMouseUp}),v.a.createElement("span",{className:o+"-inner"},r?a:h))}}]),n}(y.Component),N=function(){var e=this;this.toggle=function(){var n=e.props.onClick,t=!e.state.checked;e.setChecked(t),n(t)},this.handleKeyDown=function(n){37===n.keyCode?e.setChecked(!1):39===n.keyCode?e.setChecked(!0):32!==n.keyCode&&13!==n.keyCode||e.toggle()},this.handleMouseUp=function(n){e.node&&e.node.blur(),e.props.onMouseUp&&e.props.onMouseUp(n)},this.saveNode=function(n){e.node=n}};M.propTypes={className:m.a.string,prefixCls:m.a.string,disabled:m.a.bool,checkedChildren:m.a.any,unCheckedChildren:m.a.any,onChange:m.a.func,onMouseUp:m.a.func,onClick:m.a.func,tabIndex:m.a.number,checked:m.a.bool,defaultChecked:m.a.bool,autoFocus:m.a.bool},M.defaultProps={prefixCls:"rc-switch",checkedChildren:null,unCheckedChildren:null,className:"",defaultChecked:!1,onChange:o,onClick:o},n.default=M}});