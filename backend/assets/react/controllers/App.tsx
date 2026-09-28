import React, { lazy, Suspense } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { AuthProvider, homePathFor, portalPath, RequireRole, ROLE_CLIENT, ROLE_SUPER_ADMIN, STAFF_ROLES, useAuth } from '../lib/auth';
import { ThemeFollowsSession, ThemeProvider } from '../lib/theme';
import Layout from '../components/Layout';
import LoginPage from '../pages/LoginPage';
import AcceptInvitationPage from '../pages/AcceptInvitationPage';
import NotFoundPage from '../pages/NotFoundPage';
import PortalLoginPage from '../pages/portal/PortalLoginPage';
import { FullPageLoading } from '../components/ui';

// Every page behind a login is its own chunk, fetched the first time it is opened: the sign-in screens do not
// download the admin, and a client never downloads it at all. Layout shows a spinner in the page area while a
// chunk loads (its <Suspense>).
const AdminHomePage = lazy(() => import('../pages/admin/HomePage'));
const AdminSettingsPage = lazy(() => import('../pages/admin/SettingsPage'));
const AdminPagesPage = lazy(() => import('../pages/admin/PagesPage'));
const AdminPageEditorPage = lazy(() => import('../pages/admin/PageEditorPage'));
const AdminContactsPage = lazy(() => import('../pages/admin/ContactsPage'));
const AdminContactPage = lazy(() => import('../pages/admin/ContactPage'));
const AdminAgendaPage = lazy(() => import('../pages/admin/AgendaPage'));
const AdminPlansPage = lazy(() => import('../pages/admin/PlansPage'));
const AdminPaymentsPage = lazy(() => import('../pages/admin/PaymentsPage'));
const AdminFlowsPage = lazy(() => import('../pages/admin/FlowsPage'));
const AdminFlowEditorPage = lazy(() => import('../pages/admin/FlowEditorPage'));
const PlatformHomePage = lazy(() => import('../pages/platform/HomePage'));
const PlatformAccountsPage = lazy(() => import('../pages/platform/AccountsPage'));
const PlatformAccountPage = lazy(() => import('../pages/platform/AccountPage'));
const PlatformSettingsPage = lazy(() => import('../pages/platform/SettingsPage'));
const PlatformEmailsPage = lazy(() => import('../pages/platform/EmailsPage'));
const PortalHomePage = lazy(() => import('../pages/portal/HomePage'));
const PortalSessionsPage = lazy(() => import('../pages/portal/SessionsPage'));
const PortalPlansPage = lazy(() => import('../pages/portal/PlansPage'));
const PortalNotesPage = lazy(() => import('../pages/portal/NotesPage'));
const PortalFilesPage = lazy(() => import('../pages/portal/FilesPage'));
const PortalAccountPage = lazy(() => import('../pages/portal/AccountPage'));
const ForgotPasswordPage = lazy(() => import('../pages/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('../pages/ResetPasswordPage'));

/** The page Symfony serves at "/" is public (PublicController); inside the app, "/" means "my home". */
function HomeRedirect() {
    const { token, roles, claims } = useAuth();
    return <Navigate to={token ? homePathFor(roles, claims?.accountSlug) : '/login'} replace />;
}

/** A client's portal, behind its own sign-in at the same consultant's address. */
function PortalGate() {
    const { slug = '' } = useParams();
    return (
        <RequireRole role={ROLE_CLIENT} loginPath={`${portalPath(slug)}/ingresar`}>
            <PortalOfThisConsultant slug={slug} />
        </RequireRole>
    );
}

/** A client who opens another consultant's portal goes to their own; the API would refuse the other's data anyway. */
function PortalOfThisConsultant({ slug }: { slug: string }) {
    const { me } = useAuth();
    const own = me?.account?.slug;
    if (own && own !== slug) {
        return <Navigate to={portalPath(own)} replace />;
    }
    return <Layout />;
}

// The theme is the browser's last until /api/me says the person's.
export default function App() {
    return (
        <ThemeProvider>
            <BrowserRouter>
                <AuthProvider>
                    <ThemeFollowsSession />
                    <Suspense fallback={<FullPageLoading />}>
                        <Routes>
                            <Route path="/" element={<HomeRedirect />} />
                            <Route path="/login" element={<LoginPage />} />
                            <Route path="/invitacion" element={<AcceptInvitationPage />} />
                            <Route path="/olvide" element={<ForgotPasswordPage />} />
                            <Route path="/restablecer" element={<ResetPasswordPage />} />

                            <Route
                                path="/admin"
                                element={
                                    <RequireRole role={STAFF_ROLES}>
                                        <Layout />
                                    </RequireRole>
                                }
                            >
                                <Route index element={<AdminHomePage />} />
                                <Route path="agenda" element={<AdminAgendaPage />} />
                                <Route path="prospectos" element={<AdminContactsPage />} />
                                <Route path="prospectos/:id" element={<AdminContactPage />} />
                                <Route path="flujos" element={<AdminFlowsPage />} />
                                <Route path="flujos/:id" element={<AdminFlowEditorPage />} />
                                <Route path="planes" element={<AdminPlansPage />} />
                                <Route path="pagos" element={<AdminPaymentsPage />} />
                                <Route path="paginas" element={<AdminPagesPage />} />
                                <Route path="paginas/:id" element={<AdminPageEditorPage />} />
                                <Route path="ajustes" element={<AdminSettingsPage />} />
                            </Route>

                            <Route
                                path="/plataforma"
                                element={
                                    <RequireRole role={ROLE_SUPER_ADMIN}>
                                        <Layout />
                                    </RequireRole>
                                }
                            >
                                <Route index element={<PlatformHomePage />} />
                                <Route path="asesores" element={<PlatformAccountsPage />} />
                                <Route path="asesores/:id" element={<PlatformAccountPage />} />
                                <Route path="configuracion" element={<PlatformSettingsPage />} />
                                <Route path="correos" element={<PlatformEmailsPage />} />
                            </Route>

                            <Route path="/:slug/portal/ingresar" element={<PortalLoginPage />} />
                            <Route path="/:slug/portal/olvide" element={<ForgotPasswordPage />} />
                            <Route path="/:slug/portal" element={<PortalGate />}>
                                <Route index element={<PortalHomePage />} />
                                <Route path="sesiones" element={<PortalSessionsPage />} />
                                <Route path="planes" element={<PortalPlansPage />} />
                                <Route path="notas" element={<PortalNotesPage />} />
                                <Route path="archivos" element={<PortalFilesPage />} />
                                <Route path="cuenta" element={<PortalAccountPage />} />
                            </Route>

                            <Route path="*" element={<NotFoundPage />} />
                        </Routes>
                    </Suspense>
                </AuthProvider>
            </BrowserRouter>
        </ThemeProvider>
    );
}
