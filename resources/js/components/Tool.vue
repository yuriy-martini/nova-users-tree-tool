<template>
    <div>
        <heading class="mb-6">{{ __('Users tree') }}</heading>

        <card class="flex-col p-4 justify-center" style="min-height: 300px">
            <template v-if="authorizedToSearch">
                <input class="tree-search-input w-1/4" type="text" v-model.lazy="searchWord" :placeholder="__('Search... (min 3 char)')"/>

                <div class="w-1/4 tree-search-buttons">
                    <button class=" tree-reset-btn " type="button" @click="reset">{{ __('Reset') }}</button>

                    <vue-button-spinner class="tree-search-btn"
                                        :is-loading="isLoading"
                                        :disabled="isLoading"
                                        :status="status"
                                        v-on:click.native="search">
                        <span>{{ __('Submit') }}</span>
                    </vue-button-spinner>

                    <vue-button-spinner :disabled="isLoading"
                                        :status="status"
                                        v-on:click.native="search">
                        <span>{{ __('Search more...') }}</span>
                    </vue-button-spinner>
                </div>
            </template>

            <transition name="fade">
                <div class="spinner" v-show="!dataLoaded">
                    <looping-rhombuses-spinner
                        :animation-duration="3000"
                        :rhombus-size="30"
                        :color="'#336699'"
                    />
                </div>
            </transition>

            <div class="tree">
                <v-tree ref='tree'
                        :data="data"
                        :control="control"
                        :canDeleteRoot="true"
                        @node-expand="expand"
                        @node-click="click">
                </v-tree>
            </div>
        </card>
    </div>
</template>

<script>
    import "vue-tree-halower/dist/halower-tree.min.css";
    import 'epic-spinners/dist/lib/epic-spinners.min.css';

    import VTree from 'vue-tree-halower';
    import {LoopingRhombusesSpinner} from 'epic-spinners'
    import VueButtonSpinner from 'vue-button-spinner';

    export default {
        name: 'HelloWorld',
        components: {
            VTree,
            LoopingRhombusesSpinner,
            VueButtonSpinner,
        },
        data () {
            return {
                dataLoaded: false,
                data: [],
                filteredIds: [],
                searchWord: '',
                canCheck: false,
                control: false,
                // bgColor: '#339999',
                // fontColor: 'white'
                isLoading: false,
                status: '',
            }
        },
        mounted () {
            this.initData();
        },
        methods: {
            initData() {
                Nova.request()
                    .get('/nova-vendor/users-tree')
                    .then(({ data }) => {
                        this.dataLoaded = true;
                        this.data = data;
                    })
            },
            filter(nodeData) {
                return nodeData.title.toLowerCase().includes(this.searchWord.toLowerCase())
                    || nodeData.email.toLowerCase().includes(this.searchWord.toLowerCase())
                    || nodeData.id.toString().includes(this.searchWord.toLowerCase());
            },
            reset () {
                if (this.isLoading)
                    return;

                this.searchWord = '';
                this.$refs.tree.searchNodes('');
            },
            search () {
                this.searchWord = this.searchWord.trim();
                if (this.searchWord.length < 3)
                    return;

                this.isLoading = true;

                let loadedIds = this.getLoadedIds();

                Nova.request()
                    .post('/nova-vendor/users-tree/search', {word: this.searchWord, exclude: loadedIds})
                    .then(({ data }) => {
                        for (let i = 0; i < data.length; i++) {
                            this.data = this.mergeTreeNode2(this.data, data[i]);
                        }

                        this.filteredIds = [];
                        this.filterData();

                        this.$refs.tree.searchNodes(this.searchFilter);
                        this.isLoading = false;
                        // this.status = true // or success
                    })
                    .catch(error => {
                        console.log(error);
                        this.isLoading = false;
                        // this.status = false // or success
                    });
            },
            filterData(data = this.data){
                let item;
                for (let i = 0; i < data.length; i++) {
                    item = data[i];
                    if (this.filter(item))
                        this.filteredIds.push(item.id);

                    this.filterData(item.children);
                }
            },
            getLoadedIds(data = this.data){
                let ret = [];
                let children;
                for (let i = 0; i < data.length; i++) {
                    let node = data[i];
                    ret.push(node.id);
                    children = this.getLoadedIds(node.children);
                    for (let i = 0; i < children.length; i++) {
                        ret.push(children[i]);
                    }
                }

                return ret;
            },
            searchFilter (node) {
                return this.filteredIds.includes(node.id);
            },
            mergeTreeNode2(brothers, node){
                let new_node = this.checkNodePresent(brothers, node);

                for (let i = 0; i < node.children.length; i++) {
                    let child = node.children[i];
                    new_node.children = this.mergeTreeNode2(new_node.children, child);
                }

                return this.addOrReplaceNode(brothers, new_node);

            },
            checkNodePresent(brothers, node){
                for (let i = 0; i < brothers.length; i++) {
                    if (brothers[i].id === node.id)
                        return brothers[i];
                }
                return node;
            },
            addOrReplaceNode(brothers, node){
                for (let i = 0; i < brothers.length; i++) {
                    if (brothers[i].id === node.id){
                        brothers[i] = node;
                        return brothers;
                    }
                }
                brothers.push(node);
                return brothers;
            },
            expand (node, expand, position) {
                if (expand === false)
                    return;

                if (typeof node.children === 'undefined' || node.children.length === 0){
                    this.$set(node, 'loading', true);
                    this.$set(node, 'children', []);

                    Nova.request().get(`/nova-vendor/users-tree/${node.id}/?level=${position.level}`)
                        .then(({ data }) => {
                            this.$refs.tree.addNodes(node, data.children);
                            this.$set(node, 'loading', false);
                        })
                        .catch(error => {});
                }
            },
            click (node){
                this.$set(node, 'selected', false);
                if (Nova.config.authorizedToOpenUser){
                    window.open( node.link );
                }
            },

        },
        computed: {
            authorizedToSearch() {
                return Nova.config.authorizedToSearch;
            },
        }
    }
</script>

<style>
    /* Scoped Styles */
    .tree-search-input {
        /*width: 70%;*/
        padding: 6px 8px;
        outline: none;
        border-radius: 6px;
        border: 1px solid #ccc;
    }
    .tree-search-buttons{
        margin: 10px 0;
    }
    .tree-search-btn {
        /*width: 25%;*/
        padding: 6px 8px;
        outline: none;
        border-radius: 6px;
        border: 1px solid #e2e1e1;
        background: #336699;
        color: white;
    }
    .tree-reset-btn {
        /*width: 25%;*/
        padding: 6px 8px;
        outline: none;
        border-radius: 6px;
        background-color: #dadada;
        border: 1px solid #e2e1e1;
        color: #757575;
    }
    h1, h2 {
        font-weight: normal;
    }
    ul {
        list-style-type: none;
        padding: 0;
    }
    li {
        /*display: inline-block;*/
        margin: 0 10px;
    }
    .spinner{
        margin: 20px 0;
    }
    .halo-tree li::after,
    .halo-tree li::before {
        border-width: 0;
    }
    .halo-tree li::after {
        border-top: 1px dashed #999;
    }
    .halo-tree li::before {
        border-left: 1px dashed #999;
    }
    .halo-tree li.leaf::after {
        border-width: 0;
        border-top: 1px dashed #999;
    }
    a {
        color: #42b983;
    }
    .tree {
        padding: 20px;
    }
</style>
