flklrJsonp([227],{1051:function(e,t,n){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var r,i=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)Object.prototype.hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},o=n(0),l=(r=o)&&r.__esModule?r:{default:r};var a={display:"inline-block",position:"relative"},u={bottom:0,height:"100%",left:0,margin:0,opacity:0,padding:0,position:"absolute",right:0,top:0,width:"100%"};t.default=function(e){var t=e.children,n=e.className,r=e.onChange,o=function(e,t){var n={};for(var r in e)t.indexOf(r)>=0||Object.prototype.hasOwnProperty.call(e,r)&&(n[r]=e[r]);return n}(e,["children","className","onChange"]);return l.default.createElement("div",{className:"react-fine-uploader-file-input-container "+(n||""),style:a},t,l.default.createElement("input",i({},o,{className:"react-fine-uploader-file-input",onChange:r,style:u,type:"file"})))}},1065:function(e,t,n){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var r=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)Object.prototype.hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},i=function(){function defineProperties(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(e,t,n){return t&&defineProperties(e.prototype,t),n&&defineProperties(e,n),e}}(),o=n(0),l=_interopRequireDefault(o),a=_interopRequireDefault(n(1)),u=_interopRequireDefault(n(1051));function _interopRequireDefault(e){return e&&e.__esModule?e:{default:e}}var s=function(e){function FileInput(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,FileInput);var e=function(e,t){if(!e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return!t||"object"!=typeof t&&"function"!=typeof t?e:t}(this,(FileInput.__proto__||Object.getPrototypeOf(FileInput)).call(this));return e.state={key:c()},e._onFilesSelected=p.bind(e),e}return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}(FileInput,o.Component),i(FileInput,[{key:"render",value:function(){var e=this.props,t=e.text,n=(e.uploader,function(e,t){var n={};for(var r in e)t.indexOf(r)>=0||Object.prototype.hasOwnProperty.call(e,r)&&(n[r]=e[r]);return n}(e,["text","uploader"]));return l.default.createElement(u.default,r({},n,{key:this.state.key,onChange:this._onFilesSelected}),this.props.children?this.props.children:l.default.createElement("span",null,n.multiple?t.selectFiles:t.selectFile))}},{key:"_resetInput",value:function(){this.setState({key:c()})}}]),FileInput}();s.propTypes={text:a.default.shape({selectFile:a.default.string,selectFiles:a.default.string}),uploader:a.default.object.isRequired},s.defaultProps={text:{selectFile:"Select a File",selectFiles:"Select Files"}};var p=function(e){this.props.uploader.methods.addFiles(e.target),this._resetInput()},c=function(){return Date.now()};t.default=s}});