webpackJsonppanneau([49],{1508:function(e,t,n){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var a={MAC_ENTER:3,BACKSPACE:8,TAB:9,NUM_CENTER:12,ENTER:13,SHIFT:16,CTRL:17,ALT:18,PAUSE:19,CAPS_LOCK:20,ESC:27,SPACE:32,PAGE_UP:33,PAGE_DOWN:34,END:35,HOME:36,LEFT:37,UP:38,RIGHT:39,DOWN:40,PRINT_SCREEN:44,INSERT:45,DELETE:46,ZERO:48,ONE:49,TWO:50,THREE:51,FOUR:52,FIVE:53,SIX:54,SEVEN:55,EIGHT:56,NINE:57,QUESTION_MARK:63,A:65,B:66,C:67,D:68,E:69,F:70,G:71,H:72,I:73,J:74,K:75,L:76,M:77,N:78,O:79,P:80,Q:81,R:82,S:83,T:84,U:85,V:86,W:87,X:88,Y:89,Z:90,META:91,WIN_KEY_RIGHT:92,CONTEXT_MENU:93,NUM_ZERO:96,NUM_ONE:97,NUM_TWO:98,NUM_THREE:99,NUM_FOUR:100,NUM_FIVE:101,NUM_SIX:102,NUM_SEVEN:103,NUM_EIGHT:104,NUM_NINE:105,NUM_MULTIPLY:106,NUM_PLUS:107,NUM_MINUS:109,NUM_PERIOD:110,NUM_DIVISION:111,F1:112,F2:113,F3:114,F4:115,F5:116,F6:117,F7:118,F8:119,F9:120,F10:121,F11:122,F12:123,NUMLOCK:144,SEMICOLON:186,DASH:189,EQUALS:187,COMMA:188,PERIOD:190,SLASH:191,APOSTROPHE:192,SINGLE_QUOTE:222,OPEN_SQUARE_BRACKET:219,BACKSLASH:220,CLOSE_SQUARE_BRACKET:221,WIN_KEY:224,MAC_FF_META:224,WIN_IME:229};a.isTextModifyingKeyEvent=function(e){var t=e.keyCode;if(e.altKey&&!e.ctrlKey||e.metaKey||t>=a.F1&&t<=a.F12)return!1;switch(t){case a.ALT:case a.CAPS_LOCK:case a.CONTEXT_MENU:case a.CTRL:case a.DOWN:case a.END:case a.ESC:case a.HOME:case a.INSERT:case a.LEFT:case a.MAC_FF_META:case a.META:case a.NUMLOCK:case a.NUM_CENTER:case a.PAGE_DOWN:case a.PAGE_UP:case a.PAUSE:case a.PRINT_SCREEN:case a.RIGHT:case a.SHIFT:case a.UP:case a.WIN_KEY:case a.WIN_KEY_RIGHT:return!1;default:return!0}},a.isCharacterKey=function(e){if(e>=a.ZERO&&e<=a.NINE)return!0;if(e>=a.NUM_ZERO&&e<=a.NUM_MULTIPLY)return!0;if(e>=a.A&&e<=a.Z)return!0;if(-1!==window.navigation.userAgent.indexOf("WebKit")&&0===e)return!0;switch(e){case a.SPACE:case a.QUESTION_MARK:case a.NUM_PLUS:case a.NUM_MINUS:case a.NUM_PERIOD:case a.NUM_DIVISION:case a.SEMICOLON:case a.DASH:case a.EQUALS:case a.COMMA:case a.PERIOD:case a.SLASH:case a.APOSTROPHE:case a.SINGLE_QUOTE:case a.OPEN_SQUARE_BRACKET:case a.BACKSLASH:case a.CLOSE_SQUARE_BRACKET:return!0;default:return!1}},t.default=a,e.exports=t.default},438:function(e,t,n){"use strict";function a(e,t){return Object.keys(t).some(function(n){return e.target===(0,f.findDOMNode)(t[n])})}function r(e,t){var n=t.min,a=t.max;return e<n||e>a}function E(e){return e.touches.length>1||"touchend"===e.type.toLowerCase()&&e.touches.length>0}function u(e,t){var n=t.marks,a=t.step,r=t.min,E=Object.keys(n).map(parseFloat);if(null!==a){var u=Math.round((e-r)/a)*a+r;E.push(u)}var s=E.map(function(t){return Math.abs(e-t)});return E[s.indexOf(Math.min.apply(Math,s))]}function s(e){var t=e.toString(),n=0;return t.indexOf(".")>=0&&(n=t.length-t.indexOf(".")-1),n}function c(e,t){return e?t.clientY:t.pageX}function i(e,t){return e?t.touches[0].clientY:t.touches[0].pageX}function N(e,t){var n=t.getBoundingClientRect();return e?n.top+.5*n.height:n.left+.5*n.width}function o(e,t){var n=t.max,a=t.min;return e<=a?a:e>=n?n:e}function _(e,t){var n=t.step,a=u(e,t);return null===n?a:parseFloat(a.toFixed(s(n)))}function M(e){e.stopPropagation(),e.preventDefault()}function O(e){switch(e.keyCode){case U.default.UP:case U.default.RIGHT:return function(e,t){return e+t.step};case U.default.DOWN:case U.default.LEFT:return function(e,t){return e-t.step};case U.default.END:return function(e,t){return t.max};case U.default.HOME:return function(e,t){return t.min};case U.default.PAGE_UP:return function(e,t){return e+2*t.step};case U.default.PAGE_DOWN:return function(e,t){return e-2*t.step};default:return}}t.__esModule=!0,t.isEventFromHandle=a,t.isValueOutOfRange=r,t.isNotTouchEvent=E,t.getClosestPoint=u,t.getPrecision=s,t.getMousePosition=c,t.getTouchPosition=i,t.getHandleCenterPosition=N,t.ensureValueInRange=o,t.ensureValuePrecision=_,t.pauseEvent=M,t.getKeyboardValueMutator=O;var f=n(119),T=n(1508),U=function(e){return e&&e.__esModule?e:{default:e}}(T)}});