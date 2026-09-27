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
const PlatformHomePage = lazy(() => import('../pages/platform/HomePage'));
const PortalHomePage = lazy(() => import('../pages/portal/HomePage'));

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

                            <Route
                                path="/admin"
                                element={
                                    <RequireRole role={STAFF_ROLES}>
                                        <Layout />
                                    </RequireRole>
                                }
                            >
                                <Route index element={<AdminHomePage />} />
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
                            </Route>

                            <Route path="/:slug/portal/ingresar" element={<PortalLoginPage />} />
                            <Route path="/:slug/portal" element={<PortalGate />}>
                                <Route index element={<PortalHomePage />} />
                            </Route>

                            <Route path="*" element={<NotFoundPage />} />
                        </Routes>
                    </Suspense>
                </AuthProvider>
            </BrowserRouter>
        </ThemeProvider>
    );
}
