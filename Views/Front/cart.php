<div id="cart">
    <?php $this->Partial("navigation") ?>
    <div id="mainContent">
        <a href="/">Home</a> › Cart
        <center>
            <table id="cartTable">
                <tr>
                    <th width="10%"></th>
                    <th width="50%">Item brand / name</th>
                    <th width="15%">Price</th>
                    <th width="15%">Amount</th>
                    <th width="10%"></th>
                </tr>
                <tr v-for="item in result">
                    <td><div class="itemImage-50" v-bind:style="'background-image: url(' + item.str_path + ')'"></div></td>
                    <td><a v-bind:href="'/detail/' + item.str_code">{{item.str_brand}} - {{item.str_name}}</a></td>
                    <td>₱{{item.dbl_price}}</td>
                    <td><input type="number" v-bind:value="item.num_amount" /></td>
                    <td><button v-on:click="DeleteCart(item.str_code);">Delete</button></td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="padding:20px;">Total price:</td>
                    <td>₱{{total}}</td>
                </tr>
            </table>
            <button style="width:40%;" onclick="window.location='/order'" :disabled="count == 0"><h3>Continue Order</h3></button>
        </center>
    </div>
</div>

<script type="module">
    import Lib from "/Resources/js/lib.js";
    new Vue({
        el: "#mainContent",
        data: { 
            result: [],
            count: 0,
            total: 0,
        },
        methods: {
            GetCart: function () {
                var self = this;
                Lib.Get("/api/cart", null,
                function (success) {
                    self.result = success.Result;
                    self.count = success.Count;
                    self.total = success.Total;
                });
            },
            DeleteCart: function (itemCode) {
                var self = this;
                Lib.Delete("/api/cart", {
                    "Code": itemCode
                });
                self.GetCart();
            }
        },
        created() {
            this.GetCart();
        }
    });
</script>