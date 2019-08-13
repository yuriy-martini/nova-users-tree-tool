Nova.booting((Vue, router, store) => {
    router.addRoutes([
        {
            name: 'users-tree',
            path: '/users-tree',
            component: require('./components/Tool'),
        },
    ])
})
