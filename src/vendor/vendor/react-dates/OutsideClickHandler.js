flklrJsonp([68],{321:function(e,t,n){Object.defineProperty(t,"__esModule",{value:!0});var r=function(){function defineProperties(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(e,t,n){return t&&defineProperties(e.prototype,t),n&&defineProperties(e,n),e}}(),i=_interopRequireDefault(n(0)),o=_interopRequireDefault(n(1)),u=n(323);function _interopRequireDefault(e){return e&&e.__esModule?e:{default:e}}var l={children:o.default.node,onOutsideClick:o.default.func},c={children:i.default.createElement("span",null),onOutsideClick:function(){return function(){}}()},a=function(e){function OutsideClickHandler(){var e;!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,OutsideClickHandler);for(var t=arguments.length,n=Array(t),r=0;r<t;r++)n[r]=arguments[r];var i=function(e,t){if(!e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return!t||"object"!=typeof t&&"function"!=typeof t?e:t}(this,(e=OutsideClickHandler.__proto__||Object.getPrototypeOf(OutsideClickHandler)).call.apply(e,[this].concat(n)));return i.onOutsideClick=i.onOutsideClick.bind(i),i.setChildNodeRef=i.setChildNodeRef.bind(i),i}return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}(OutsideClickHandler,i["default"].Component),r(OutsideClickHandler,[{key:"componentDidMount",value:function(){return function(){this.removeEventListener=(0,u.addEventListener)(document,"click",this.onOutsideClick,{capture:!0})}}()},{key:"componentWillUnmount",value:function(){return function(){this.removeEventListener&&this.removeEventListener()}}()},{key:"onOutsideClick",value:function(){return function(e){var t=this.props.onOutsideClick,n=this.childNode;n&&n.contains(e.target)||t(e)}}()},{key:"setChildNodeRef",value:function(){return function(e){this.childNode=e}}()},{key:"render",value:function(){return function(){return i.default.createElement("div",{ref:this.setChildNodeRef},this.props.children)}}()}]),OutsideClickHandler}();t.default=a,a.propTypes=l,a.defaultProps=c}});