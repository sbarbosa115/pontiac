// The React app's entry: the stylesheet, Stimulus (which mounts React on the page's #app) and the React components.
import { registerReactControllerComponents } from '@symfony/ux-react';
import './bootstrap';
import './styles/app.css';

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));
