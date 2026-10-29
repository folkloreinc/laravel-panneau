import { PanneauProvider } from '@panneau/core/contexts';
import Localized from '@panneau/field-localized';

const App = React.lazy(() => import('@panneau/app'));

export default function Panneau(props) {
    return <PanneauProvider {...props}><App fields={{ localized: Localized }} /></PanneauProvider>;
}
