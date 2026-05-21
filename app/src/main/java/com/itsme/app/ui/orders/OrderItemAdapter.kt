package com.itsme.app.ui.orders
import android.view.*
import androidx.recyclerview.widget.*
import com.itsme.app.data.entities.OrderItem
import com.itsme.app.databinding.ItemOrderItemBinding

class OrderItemAdapter(val onDelete:(OrderItem)->Unit) :
    ListAdapter<OrderItem, OrderItemAdapter.VH>(object: DiffUtil.ItemCallback<OrderItem>() {
        override fun areItemsTheSame(a: OrderItem, b: OrderItem) = a.id==b.id
        override fun areContentsTheSame(a: OrderItem, b: OrderItem) = a==b
    }) {
    inner class VH(val b: ItemOrderItemBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemOrderItemBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val i = getItem(pos)
        h.b.tvItemName.text = i.articleName
        h.b.tvQuantity.text = "x${i.quantity}"
        h.b.tvPrice.text = "\$${String.format("%.2f", i.salePrice)}"
        h.b.tvSubtotal.text = "\$${String.format("%.2f", i.salePrice * i.quantity)}"
        h.b.btnDelete.setOnClickListener { onDelete(i) }
    }
}
