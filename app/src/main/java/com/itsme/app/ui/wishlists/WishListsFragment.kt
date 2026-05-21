package com.itsme.app.ui.wishlists
import android.content.Intent
import android.os.Bundle
import android.view.*
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.databinding.FragmentWishListsBinding
import com.itsme.app.utils.SessionManager
import com.itsme.app.viewmodel.ClientViewModel
import com.itsme.app.viewmodel.WishListViewModel

class WishListsFragment : Fragment() {
    private var _b: FragmentWishListsBinding? = null
    private val b get() = _b!!
    private val vm: WishListViewModel by viewModels()
    private val cvm: ClientViewModel by viewModels()
    private var clientMap = mapOf<Int, String>()

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?) =
        FragmentWishListsBinding.inflate(i, c, false).also { _b = it }.root

    override fun onViewCreated(v: View, s: Bundle?) {
        val tid = SessionManager.getTravelId(requireContext())
        vm.setTravel(tid)
        cvm.all.observe(viewLifecycleOwner) { clientMap = it.associate { c -> c.id to c.fullName } }
        val adapter = WishListAdapter(
            onOpen = { h -> startActivity(Intent(requireContext(), WishListItemsActivity::class.java).apply { putExtra("hid", h.id); putExtra("name", h.listName) }) },
            onEdit = { h -> startActivity(Intent(requireContext(), WishListFormActivity::class.java).apply { putExtra("hid",h.id); putExtra("name",h.listName); putExtra("cid",h.clientId); putExtra("tid",h.travelDateId) }) },
            onDelete = { h -> AlertDialog.Builder(requireContext()).setTitle("Eliminar").setMessage("¿Eliminar '${h.listName}'?")
                .setPositiveButton("Eliminar") { _,_ -> vm.deleteHeader(h) }.setNegativeButton("Cancelar",null).show() },
            clientName = { id -> clientMap[id] ?: "Cliente #$id" }
        )
        b.rvWishLists.layoutManager = LinearLayoutManager(requireContext())
        b.rvWishLists.adapter = adapter
        vm.headers.observe(viewLifecycleOwner) { adapter.submitList(it) }
        b.fabAddWishList.setOnClickListener {
            startActivity(Intent(requireContext(), WishListFormActivity::class.java).apply { putExtra("tid", tid) })
        }
    }
    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
