flklrJsonp([66],{308:function(e,t,o){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var n=function(){function defineProperties(e,t){for(var o=0;o<t.length;o++){var n=t[o];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(e,t,o){return t&&defineProperties(e.prototype,t),o&&defineProperties(e,o),e}}(),r=_interopRequireDefault(o(13)),a=_interopRequireDefault(o(1)),u=_interopRequireDefault(o(0));function _interopRequireDefault(e){return e&&e.__esModule?e:{default:e}}var l=function(e){function Value(e){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,Value);var t=function(e,t){if(!e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return!t||"object"!=typeof t&&"function"!=typeof t?e:t}(this,(Value.__proto__||Object.getPrototypeOf(Value)).call(this,e));return t.handleMouseDown=t.handleMouseDown.bind(t),t.onRemove=t.onRemove.bind(t),t.handleTouchEndRemove=t.handleTouchEndRemove.bind(t),t.handleTouchMove=t.handleTouchMove.bind(t),t.handleTouchStart=t.handleTouchStart.bind(t),t}return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}(Value,u.default.Component),n(Value,[{key:"handleMouseDown",value:function(e){if("mousedown"!==e.type||0===e.button)return this.props.onClick?(e.stopPropagation(),void this.props.onClick(this.props.value,e)):void(this.props.value.href&&e.stopPropagation())}},{key:"onRemove",value:function(e){e.preventDefault(),e.stopPropagation(),this.props.onRemove(this.props.value)}},{key:"handleTouchEndRemove",value:function(e){this.dragging||this.onRemove(e)}},{key:"handleTouchMove",value:function(){this.dragging=!0}},{key:"handleTouchStart",value:function(){this.dragging=!1}},{key:"renderRemoveIcon",value:function(){if(!this.props.disabled&&this.props.onRemove)return u.default.createElement("span",{className:"Select-value-icon","aria-hidden":"true",onMouseDown:this.onRemove,onTouchEnd:this.handleTouchEndRemove,onTouchStart:this.handleTouchStart,onTouchMove:this.handleTouchMove},"×")}},{key:"renderLabel",value:function(){return this.props.onClick||this.props.value.href?u.default.createElement("a",{className:"Select-value-label",href:this.props.value.href,target:this.props.value.target,onMouseDown:this.handleMouseDown,onTouchEnd:this.handleMouseDown},this.props.children):u.default.createElement("span",{className:"Select-value-label",role:"option","aria-selected":"true",id:this.props.id},this.props.children)}},{key:"render",value:function(){return u.default.createElement("div",{className:(0,r.default)("Select-value",this.props.value.className),style:this.props.value.style,title:this.props.value.title},this.renderRemoveIcon(),this.renderLabel())}}]),Value}();l.propTypes={children:a.default.node,disabled:a.default.bool,id:a.default.string,onClick:a.default.func,onRemove:a.default.func,value:a.default.object.isRequired},t.default=l}});